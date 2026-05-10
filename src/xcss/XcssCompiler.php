<?php

namespace EorBah545\Eorbahapi\xcss;

class XcssCompiler {
    public function __construct() {}

    /**
     * Ajoute le fichier principal ou l'extension si nécessaire
     */
    private function auto_ext($root, $main, $ext) {
        $finalPath = $root;
        if (substr($finalPath, -1) === '/') {
            $finalPath .= $main;                    // dossier -> fichier __init__.xcss
        } elseif (substr($finalPath, -strlen($ext)) !== $ext) {
            $finalPath .= $ext;                     // pas la bonne extension -> on l'ajoute
        }
        return $finalPath;
    }

    /**
     * Minification basique du CSS (identique au code JS)
     */
    public function minifyCSS($css) {
        $minified = preg_replace('#/\*[\s\S]*?\*/#', '', $css);   // supprime commentaires
        $minified = preg_replace('/\s+/', ' ', $minified);        // espaces multiples → un seul
        $minified = preg_replace('/\s*([{}:;,])\s*/', '$1', $minified); // espaces autour des symboles
        $minified = preg_replace('/;\s*}/', '}', $minified);      // ;} → }
        return trim($minified);
    }

    /**
     * Retourne le répertoire parent (en gardant le slash final)
     */
    private function removeLastFile($url) {
        $lastSlash = strrpos($url, '/');
        if ($lastSlash === false) {
            return '';
        }
        return (substr($url, -1) === '/') ? $url : substr($url, 0, $lastSlash + 1);
    }

    /**
     * Fusionne une URL de base et un chemin relatif sans doubler les slashes
     * (sauf après le protocole, ex: http://)
     */
    private function mergeURLs($baseURL, $relativePath) {
        if (substr($baseURL, -1) !== '/') {
            $baseURL .= '/';
        }
        $merged = $baseURL . $relativePath;
        // Évite les doubles slashes sauf ceux du protocole (ex: http://)
        $merged = preg_replace('#(?<!:)/{2,}#', '/', $merged);
        return $merged;
    }

    /**
     * Lit le contenu d'un fichier (version PHP de la fonction openx)
     * Lève une exception en cas d'échec
     */
    private function openx($path) {
        if (!file_exists($path)) {
            throw new \RuntimeException("xcss: file not found: $path");
        }
        if (!is_readable($path)) {
            throw new \RuntimeException("xcss: file not readable: $path");
        }
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("xcss: read failed for file: $path");
        }
        return $content;
    }

    /**
     * Compile le code XCSS : importation et variables
     */
    private function compile($code, $xcss_root) {
        $updatedCode = $code;
        $variables = [];

        // Expressions régulières
        $includeRegex = '/@import\s+[\'"]([^\'"]+)[\'"]\s*;/';
        $variableRegex = '/\$([\w-]+)\s*:\s*([^;]+);/';

        // **1. Gestion des @include (récursifs)**
        $maxIterations = 100; // sécurité anti-boucle infinie
        $iterations = 0;
        while (preg_match($includeRegex, $updatedCode, $match) && $iterations < $maxIterations) {
            $filePath = $match[1];
            $file_import = $this->mergeURLs($xcss_root, $filePath);
            $fileContent = $this->openx($file_import);
            $updatedCode = str_replace($match[0], $fileContent, $updatedCode);
            $iterations++;
        }

        // **2. Gestion des variables : suppression des déclarations**
        $updatedCode = preg_replace_callback($variableRegex, function($matches) use (&$variables) {
            $variables[$matches[1]] = trim($matches[2]);
            return ''; // supprime la ligne de déclaration
        }, $updatedCode);

        // **3. Remplacement des variables dans le reste du code**
        $updatedCode = preg_replace_callback('/\$([\w-]+)/', function($matches) use ($variables) {
            $varName = $matches[1];
            return isset($variables[$varName]) ? $variables[$varName] : $matches[0];
        }, $updatedCode);

        return $updatedCode;
    }

    /**
     * Point d'entrée principal : charge un fichier .xcss et retourne le CSS compilé
     */
    public function render($path) {
        $root = $this->auto_ext($path, '__init__.xcss', '.xcss');   // chemin final du fichier
        $xcss_root = $this->removeLastFile($root);                  // dossier racine
        $xcss_code = $this->openx($root);                           // contenu brut
        $compiled_code = $this->compile($xcss_code, $xcss_root);    // compilation
        return $compiled_code;
    }
}
