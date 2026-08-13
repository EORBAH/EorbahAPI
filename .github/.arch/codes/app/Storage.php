<?php

namespace PhoenixAccount;

class Storage {
    public function sendFile($filePath): void {
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

        header('Cache-Control: no-cache, no-store, must-revalidate');

        $compressed = false;
        if ($this->isCompressible($mimeType)) {
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

    public function sanitizePath($path): string {
        $path = str_replace(['../', '..\\'], '', $path);

        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#\\\\+#', '\\\\', $path);

        // Supprimer les slashs initiaux et finaux
        $path = trim($path, '/\\');

        return $path;
    }

    public function isInDirectory($filePath): bool {
        $realPath = realpath($filePath);
        return $realPath;
    }



    public function getMimeType($filePath): bool|string {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $mimeTypes = [
            'json' => 'application/json',
            'webp' => 'image/webp',
        ];

        return $mimeTypes[$extension] ?? mime_content_type($filePath) ?: 'application/octet-stream';
    }

    public function isCompressible($mimeType): bool {
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

    public function handleCompression($filePath, $mimeType): bool {
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

    public function isNotModified($lastModified, $etag): bool {
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