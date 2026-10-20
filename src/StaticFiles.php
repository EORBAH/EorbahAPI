<?php

namespace Eorbahapi;

use Eorbahapi\Exceptions\EorbahAPIError;

/**
 * Sert un répertoire de fichiers statiques.
 *
 *     $app->mount('/static', new StaticFiles(__DIR__ . '/public'));
 *
 * L'application montée remplit la réponse partagée (`process()`) : statut,
 * en-têtes (Content-Type, Last-Modified, ETag, Cache-Control), éventuelle
 * compression (`Content-Encoding`) et corps. Rien n'est envoyé ici : c'est le
 * pipeline d'EorbahAPI (ou `run()` en usage autonome) qui envoie la réponse,
 * ce qui rend le service testable via `TestClient`.
 */
class StaticFiles extends BaseMountedApplication
{
    private string $directory;
    private string $indexFile;
    private string $cacheControl;
    private bool $compression;
    private array $allowedExtensions;

    public function __construct(string $directory, $options = [])
    {
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
            throw new EorbahAPIError("Le répertoire '$directory' n'existe pas");
        }
    }

    /**
     * Remplit la réponse (statut, en-têtes, corps) pour le chemin courant.
     */
    public function process(): void
    {
        $path = $this->request->path();

        if (!$this->serve($path)) {
            $this->response->status = 404;
            $this->response->body = 'File not found';
        }
    }

    /**
     * Sert un chemin relatif au répertoire, en remplissant `$this->response`.
     *
     * @return bool false si le fichier n'existe pas ou n'est pas autorisé.
     */
    public function serve(string $path): bool
    {
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

        return $this->serveFile($filePath);
    }

    private function sanitizePath(string $path): string
    {
        $path = str_replace(['../', '..\\'], '', $path);

        $path = preg_replace('#/+#', '/', $path);
        $path = preg_replace('#\\\\+#', '\\\\', $path);

        $path = trim($path, '/\\');

        return $path;
    }

    private function isAllowedExtension(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($extension, $this->allowedExtensions);
    }

    private function isInDirectory(string $filePath): bool
    {
        $realPath = realpath($filePath);
        return $realPath && strpos($realPath, $this->directory) === 0;
    }

    /**
     * En-tête de la requête courante (ou null hors requête).
     */
    private function requestHeader(string $name): ?string
    {
        $request = $this->request ?? null;

        return $request instanceof Request ? $request->getHeader($name) : null;
    }

    private function serveFile(string $filePath): bool
    {
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');

        $fileSize = filesize($filePath);
        $lastModified = filemtime($filePath);
        $etag = md5($filePath . $lastModified . $fileSize);
        $mimeType = $this->getMimeType($filePath);

        $this->response->headers['Content-Type'] = $mimeType;
        $this->response->headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $lastModified) . ' GMT';
        $this->response->headers['ETag'] = '"' . $etag . '"';

        if ($this->cacheControl) {
            $this->response->headers['Cache-Control'] = $this->cacheControl;
        }

        if ($this->isNotModified($lastModified, $etag)) {
            $this->response->status = 304;
            $this->response->body = null;

            return true;
        }

        $content = (string) file_get_contents($filePath);

        if ($this->compression && $this->isCompressible($mimeType) && $this->tryCompress($content)) {
            return true;
        }

        $this->response->headers['Content-Length'] = $fileSize;
        $this->response->body = $content;

        return true;
    }

    private function getMimeType(string $filePath): bool|string
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

    private function isCompressible(string $mimeType): bool
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

    /**
     * Compresse le corps lorsque le client accepte gzip (ou brotli si
     * l'extension est disponible). Retourne true si le corps a été remplacé.
     */
    private function tryCompress(string $content): bool
    {
        $acceptEncoding = (string) $this->requestHeader('Accept-Encoding');
        $originalSize = strlen($content);

        $encoding = null;
        $compressedContent = null;

        if (strpos($acceptEncoding, 'br') !== false && function_exists('brotli_compress')) {
            $encoding = 'br';
            $compressedContent = \brotli_compress($content, 4);
        } elseif (strpos($acceptEncoding, 'gzip') !== false) {
            $encoding = 'gzip';
            $compressedContent = gzencode($content, 6);
        }

        if ($compressedContent === false || $compressedContent === null || $compressedContent === '') {
            return false;
        }

        // Compression inefficace : on sert le contenu original.
        if (strlen($compressedContent) >= $originalSize) {
            return false;
        }

        $this->response->headers['Content-Encoding'] = $encoding;
        $this->response->headers['Content-Length'] = strlen($compressedContent);
        $this->response->headers['Vary'] = 'Accept-Encoding';
        $this->response->body = $compressedContent;

        return true;
    }

    private function isNotModified($lastModified, $etag): bool
    {
        $ifModifiedSince = $this->requestHeader('If-Modified-Since');

        if ($ifModifiedSince !== null && $ifModifiedSince !== '') {
            $timestamp = strtotime($ifModifiedSince);
            if ($timestamp && $lastModified <= $timestamp) {
                return true;
            }
        }

        $ifNoneMatch = $this->requestHeader('If-None-Match');

        if ($ifNoneMatch !== null && trim($ifNoneMatch, "\"'") === $etag) {
            return true;
        }

        return false;
    }
}
