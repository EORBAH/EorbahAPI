<?php

namespace Eorbahapi;


class StaticFiles {
    private $directory;
    private $indexFile;
    private $cacheControl;
    private $compression;
    private $allowedExtensions;

    /** @var Request|null */
    private $request;

    /** @var Response|null */
    private $response;

    public function __construct($directory, $options = []) {
        $this->directory = realpath($directory);
        $this->indexFile = $options['index'] ?? 'index.html';
        $this->cacheControl = $options['cache_control'] ?? 'no-cache, no-store, must-revalidate';
        $this->compression = $options['compression'] ?? true;
        $this->allowedExtensions = $options['allowed_extensions'] ?? [
            'html',
            'css',
            'js',
            'jsx',
            'json',
            'xml',
            'png',
            'jpg',
            'jpeg',
            'gif',
            'ico',
            'svg',
            'ttf',
            'woff',
            'woff2',
            'pdf',
            'txt',
            'md',
            'webmanifest',
            'tx',
            'eot',
            'xcss'
        ];

        if (!$this->directory || !is_dir($this->directory)) {
            throw new \Exception("Le répertoire '$directory' n'existe pas");
        }
    }

    /**
     * Permet à EorbahAPI d'injecter les instances Request/Response partagées.
     * Appelé automatiquement par mount().
     */
    public function setRequestResponse($request, $response): void {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Point d'entrée principal appelé par mount().
     * Compatible avec l'interface attendue par EorbahAPI::mount().
     *
     * @param Request  $request
     * @param Response $response
     */
    public function handle($request, $response): void {
        $this->setRequestResponse($request, $response);

        // Récupération du chemin (après retrait du préfixe par mount)
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);

        $served = $this->serve($path);
        if (!$served) {
            if ($this->response) {
                $this->response->status(404)->send('File not found');
            } else {
                http_response_code(404);
                echo 'File not found';
            }
        }
    }

    /**
     * Compatibilité avec la signature run($http_code, $handler) de EorbahAPI.
     * Utilisé si l'application montée est appelée via run().
     */
    public function run($http_code = "404", $handler = null): void {
        if ($this->request && $this->response) {
            $this->handle($this->request, $this->response);
        } else {
            $this->handle(new Request(), new Response());
        }
    }


    public function serve($path): bool{
        $cleanPath = $this->sanitizePath($path);
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $cleanPath;

        if (is_dir($filePath)) {
            $filePath .= DIRECTORY_SEPARATOR . $this->indexFile;
            if (!file_exists($filePath)) {
                return false;
            }
        }

        if (!file_exists($filePath) || !$this->isAllowedExtension($filePath) || !$this->isInDirectory($filePath)) {
            return false;
        }

        $this->sendFile($filePath);
        return true;
    }

    private function sanitizePath($path): string {
        $path = str_replace(['../', '..\\'], '', $path);

        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#\\\\+#', '\\\\', $path);

        $path = trim($path, '/\\');

        return $path;
    }

    private function isAllowedExtension($filePath): bool {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedExtensions);
    }

    private function isInDirectory($filePath): bool {
        $realPath = realpath($filePath);
        return $realPath && strpos($realPath, $this->directory) === 0;
    }

    private function sendFile($filePath): void {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        $fileSize = filesize($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5($filePath . $lastModified . $fileSize);
        $mimeType = $this->getMimeType($filePath);

        header('Content-Type: ' . $mimeType);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: "' . $etag . '"');

        if ($this->cacheControl) {
            header('Cache-Control: ' . $this->cacheControl);
        }

        $compressed = false;
        if ($this->compression && $this->isCompressible($mimeType)) {
            $compressed = $this->handleCompression($filePath, $mimeType);
        }


        if ($this->isNotModified($lastModified, $etag)) {
            header('HTTP/1.1 304 Not Modified');
            exit;
        }

        if (!$compressed) {
            header('Content-Length: ' . $fileSize);
            readfile($filePath);
        }
    }

    private function getMimeType($filePath): bool|string {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'ttf' => 'font/ttf',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'eot' => 'application/vnd.ms-fontobject',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'md' => 'text/markdown',
            'webmanifest' => 'application/manifest+json'
        ];

        return $mimeTypes[$extension] ?? mime_content_type($filePath) ?: 'application/octet-stream';
    }

    private function isCompressible($mimeType): bool {
        $compressibleTypes = [
            'text/html',
            'text/css',
            'text/plain',
            'text/xml',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
            'application/xhtml+xml',
            'application/manifest+json'
        ];

        return in_array($mimeType, $compressibleTypes);
    }

    private function handleCompression($filePath, $mimeType): bool {
        $acceptEncoding = isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '';

        $content = file_get_contents($filePath);
        $originalSize = strlen($content);
        $compressedContent = null;
        $encoding = null;

        if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
            $encoding = 'br';
            $compressedContent = brotli_compress($content, 4);
        } elseif (strpos($acceptEncoding, 'gzip') !== false) {
            $encoding = 'gzip';
            $compressedContent = gzencode($content, 6);
        } else {
            return false;
        }

        if ($compressedContent === false || strlen($compressedContent) === 0) {
            header('Content-Length: ' . $originalSize);
            echo $content;
            return true;
        }

        $compressedSize = strlen($compressedContent);

        header('Content-Encoding: ' . $encoding);
        header('Content-Length: ' . $compressedSize);
        header('Vary: Accept-Encoding');

        echo $compressedContent;
        return true;
    }

    private function isNotModified($lastModified, $etag): bool {
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