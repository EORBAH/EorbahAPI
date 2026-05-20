<?php

namespace EorBah545\Eorbahapi\css;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

class cssFiles {
    private string $directory;
    private string $indexFile;
    private string $cacheControl;
    private bool $compression;

    private ?Request $request = null;
    private ?Response $response = null;
    private cssCompiler $cssCompiler;

    /**
     * @param string $directory Chemin absolu du dossier racine
     * @param array  $options   Options : index (fichier par défaut si dossier),
     *                          cache_control, compression
     */
    public function __construct(string $directory, array $options = [])
    {
        $this->directory = realpath($directory);
        $this->indexFile = $options['index'] ?? 'index.css';   // <-- .xcss par défaut
        $this->cacheControl = $options['cache_control'] ?? 'no-cache, no-store, must-revalidate';
        $this->compression = $options['compression'] ?? true;

        $this->cssCompiler = new cssCompiler();

        if (!$this->directory || !is_dir($this->directory)) {
            throw new \RuntimeException("Le répertoire '$directory' n'existe pas");
        }
    }

    /** Injecté automatiquement par EorbahAPI::mount() */
    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Point d'entrée principal (compatible avec l'interface de mount)
     */
    public function handle(Request $request, Response $response): void
    {
        $this->setRequestResponse($request, $response);

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        if (!$this->serve($path)) {
            if ($this->response) {
                $this->response->status(404)->send('File not found');
            } else {
                http_response_code(404);
                echo 'File not found';
            }
        }
    }

    /** Fallback pour une éventuelle utilisation avec run() */
    public function run(string $http = "404", $handler = null): void
    {
        if ($this->request && $this->response) {
            $this->handle($this->request, $this->response);
        } else {
            $this->handle(new Request(), new Response());
        }
    }

    // ---------------------------------------------------------------------
    // Service du fichier
    // ---------------------------------------------------------------------

    public function serve(string $path): bool
    {
        $cleanPath = $this->sanitizePath($path);
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $cleanPath;

        // Si c'est un dossier, on utilise le fichier index
        if (is_dir($filePath)) {
            $filePath .= DIRECTORY_SEPARATOR . $this->indexFile;
            if (!file_exists($filePath)) {
                return false;
            }
        }

        // Sécurité : le fichier doit exister, être dans le répertoire autorisé
        // et avoir l'extension .xcss
        if (!file_exists($filePath) || !$this->isAllowedExtension($filePath) || !$this->isInDirectory($filePath)) {
            return false;
        }

        $this->sendFile($filePath);
        return true;
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace(['../', '..\\'], '', $path);
        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#\\\\+#', '\\\\', $path);
        return trim($path, '/\\');
    }

    private function isAllowedExtension(string $filePath): bool
    {
        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'css';
    }

    private function isInDirectory(string $filePath): bool
    {
        $realPath = realpath($filePath);
        return $realPath && str_starts_with($realPath, $this->directory);
    }

    // ---------------------------------------------------------------------
    // Envoi du fichier compilé
    // ---------------------------------------------------------------------

    private function sendFile(string $filePath): void
    {
        // Désactiver toute compression automatique du serveur
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        // Compiler le fichier .xcss en CSS
        $compiledCss = $this->cssCompiler->render($filePath);
        if ($compiledCss === null) {
            // Erreur de compilation
            http_response_code(500);
            echo 'CSS compilation error';
            return;
        }

        // Récupérer les métadonnées à partir du fichier source original
        $lastModified = filemtime($filePath);
        $fileSize = strlen($compiledCss);
        $etag = md5($filePath . $lastModified . $fileSize);
        $mimeType = 'text/css';

        // En-têtes
        header('Content-Type: ' . $mimeType);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: "' . $etag . '"');

        if ($this->cacheControl) {
            header('Cache-Control: ' . $this->cacheControl);
        }

        // Gestion de la compression
        $compressed = false;
        if ($this->compression) {
            $compressed = $this->handleCompression($compiledCss, $mimeType);
        }

        // 304 Not Modified ?
        if ($this->isNotModified($lastModified, $etag)) {
            http_response_code(304);
            exit;
        }

        // Pas de compression → on envoie le CSS tel quel
        if (!$compressed) {
            header('Content-Length: ' . $fileSize);
            echo $compiledCss;
        }
    }

    // ---------------------------------------------------------------------
    // Compression (sur le contenu compilé, pas le fichier brut)
    // ---------------------------------------------------------------------

    private function handleCompression(string $content, string $mimeType): bool
    {
        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $compressedContent = null;
        $encoding = null;

        if (str_contains($acceptEncoding, 'br') && function_exists('brotli_compress')) {
            $encoding = 'br';
            $compressedContent = brotli_compress($content, 4);
        } elseif (str_contains($acceptEncoding, 'gzip')) {
            $encoding = 'gzip';
            $compressedContent = gzencode($content, 6);
        } else {
            return false;
        }

        if ($compressedContent === false || strlen($compressedContent) === 0) {
            header('Content-Length: ' . strlen($content));
            echo $content;
            return true;
        }

        header('Content-Encoding: ' . $encoding);
        header('Content-Length: ' . strlen($compressedContent));
        header('Vary: Accept-Encoding');
        echo $compressedContent;
        return true;
    }

    private function isNotModified(int $lastModified, string $etag): bool
    {
        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if ($ifModifiedSince && $lastModified <= $ifModifiedSince) {
                return true;
            }
        }
        if (isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
            $ifNoneMatch = trim($_SERVER['HTTP_IF_NONE_MATCH'], '"');
            if ($ifNoneMatch === $etag) {
                return true;
            }
        }
        return false;
    }
}