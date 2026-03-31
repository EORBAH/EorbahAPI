<?php

namespace EorBah545\Eorbahapi;

class StaticFiles
{
    private $directory;
    private $indexFile;
    private $cacheControl;
    private $compression;
    private $allowedExtensions; 

    public function __construct($directory, $options = [])
    {
        $this->directory = realpath($directory);
        $this->indexFile = $options['index'] ?? 'index.html';
        $this->cacheControl = $options['cache_control'] ?? 'no-cache, no-store, must-revalidate';
        $this->compression = $options['compression'] ?? true;
        $this->allowedExtensions = $options['allowed_extensions'] ?? [
            'html', 'css', 'js', 'jsx', 'json', 'xml', 'png',
            'jpg', 'jpeg', 'gif', 'ico', 'svg', 'ttf', 'woff', 'woff2',
            'pdf', 'txt', 'md', 'webmanifest', 'x', 'xcss', 'tx', 'eot'
        ];

        if (!$this->directory || !is_dir($this->directory)) {
            throw new \Exception("Le répertoire '$directory' n'existe pas");
        }
    }

    public function serve($path)
    {
        $cleanPath = $this->sanitizePath($path);

        $filePath = $this->directory . DIRECTORY_SEPARATOR . $cleanPath;

        if (is_dir($filePath)) {
            $filePath .= DIRECTORY_SEPARATOR . $this->indexFile;
            if (!file_exists($filePath)) {
                return false;
            }
        }

        if (!file_exists($filePath)) {
            return false;
        }

        if (!$this->isAllowedExtension($filePath)) {
            return false;
        }

        if (!$this->isInDirectory($filePath)) {
            return false;
        }

        $this->sendFile($filePath);
        return true;
    }

    private function sanitizePath($path)
    {
        $path = str_replace(['../', '..\\'], '', $path);

        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#\\\\+#', '\\\\', $path);

        // Supprimer les slashs initiaux et finaux
        $path = trim($path, '/\\');

        return $path;
    }

    private function isAllowedExtension($filePath)
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedExtensions);
    }

    private function isInDirectory($filePath)
    {
        $realPath = realpath($filePath);
        return $realPath && strpos($realPath, $this->directory) === 0;
    }

    private function sendFile($filePath)
    {
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

    private function getMimeType($filePath)
    {
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

    private function isCompressible($mimeType)
    {
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

    private function handleCompression($filePath, $mimeType)
    {
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

    private function isNotModified($lastModified, $etag)
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

    public function serveSpa($path)
    {
        $filePath = $this->directory . DIRECTORY_SEPARATOR . $this->sanitizePath($path);

        if (file_exists($filePath) && !is_dir($filePath) && $this->isAllowedExtension($filePath)) {
            return $this->sendFile($filePath);
        }

        $spaIndex = $this->directory . DIRECTORY_SEPARATOR . $this->indexFile;
        if (file_exists($spaIndex)) {
            return $this->sendFile($spaIndex);
        }

        return false;
    }

    private function addSecurityHeaders()
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
    }

    private function handleHeadRequest($filePath)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'HEAD') {
            header('Content-Type: ' . $this->getMimeType($filePath));
            header('Content-Length: ' . filesize($filePath));
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
            exit;
        }
    }
}
