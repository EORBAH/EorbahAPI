<?php

namespace Eorbahapi\Responses;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Réponse binaire servie depuis un fichier ou un flux.
 *
 * Elle gère les en-têtes attendus (`Content-Type`, `Content-Length`,
 * `Last-Modified`, `ETag`, `Accept-Ranges`), les requêtes conditionnelles
 * (`If-None-Match`, `If-Modified-Since` → **304**), les requêtes `Range`
 * (mono-plage → **206**, plage invalide → **416**) et le téléchargement
 * (`Content-Disposition: attachment`).
 *
 *     $app->get('/exports/{id}', function (Request $request, int $id) {
 *         return new FileResponse(__DIR__ . "/exports/$id.csv", request: $request);
 *     });
 *
 * `EorbahAPI::handle()` rattache automatiquement la requête courante à une
 * `FileResponse` : la plage et les validateurs fonctionnent sans code
 * supplémentaire. Pour un téléchargement :
 *
 *     return FileResponse::download($chemin, 'rapport.csv');
 */
class FileResponse extends Response
{
    public const CHUNK_SIZE = 8192;

    /** Types MIME connus, utilisés sans `finfo`. */
    private const MIME_TYPES = [
        'txt' => 'text/plain; charset=UTF-8',
        'text' => 'text/plain; charset=UTF-8',
        'md' => 'text/markdown; charset=UTF-8',
        'csv' => 'text/csv; charset=UTF-8',
        'html' => 'text/html; charset=UTF-8',
        'htm' => 'text/html; charset=UTF-8',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'zip' => 'application/zip',
        'gz' => 'application/gzip',
        'tar' => 'application/x-tar',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'wasm' => 'application/wasm',
    ];

    /** Statut demandé par l'utilisateur (avant 206/304/416). */
    private int $baseStatus;

    private ?string $path = null;

    /** @var resource|null */
    private mixed $stream = null;

    private ?int $size = null;

    private ?string $contentType = null;

    private ?string $etag = null;

    private ?int $lastModified = null;

    private ?string $downloadName = null;

    private string $disposition = 'inline';

    /** Position du premier octet servi. */
    private int $offset = 0;

    /** Nombre d'octets à servir (null = tout). */
    private ?int $length = null;

    private ?Request $request = null;

    private bool $rangeUnsatisfiable = false;

    /**
     * @param string|UploadFile|resource $file        Chemin, `UploadFile` ou flux ouvert.
     * @param int                        $status      Statut de départ (200 par défaut).
     * @param array<string, string>      $headers     En-têtes supplémentaires.
     * @param string|null                $filename    Nom proposé au client.
     * @param string                     $disposition `inline` ou `attachment`.
     * @param string|null                $mediaType   Type MIME forcé.
     * @param Request|null               $request     Requête pour Range/If-None-Match.
     */
    public function __construct(
        mixed $file,
        int $status = 200,
        array $headers = [],
        ?string $filename = null,
        string $disposition = 'inline',
        ?string $mediaType = null,
        ?Request $request = null,
    ) {
        $this->baseStatus = $status;
        $this->disposition = in_array($disposition, ['inline', 'attachment'], true) ? $disposition : 'inline';

        $this->loadSource($file);

        if ($mediaType !== null && $mediaType !== '') {
            $this->contentType = $mediaType;
        }

        if ($filename !== null && $filename !== '') {
            $this->downloadName = $this->sanitizeFilename($filename);
        }

        parent::__construct(body: null, status: $status, headers: $headers);

        $this->applyMetadataHeaders();

        if ($request !== null) {
            $this->withRequest($request);
        }
    }

    /**
     * Réponse en pièce jointe (`Content-Disposition: attachment`).
     */
    public static function download(
        string $path,
        ?string $filename = null,
        ?Request $request = null,
        array $headers = [],
    ): self {
        return new self(
            file: $path,
            headers: $headers,
            filename: $filename ?? basename($path),
            disposition: 'attachment',
            request: $request,
        );
    }

    /**
     * Réponse depuis un fichier téléversé.
     */
    public static function fromUploadFile(
        UploadFile $file,
        ?string $filename = null,
        ?Request $request = null,
        array $headers = [],
    ): self {
        return new self(
            file: $file,
            headers: $headers,
            filename: $filename ?? $file->filename,
            mediaType: $file->content_type ?: null,
            request: $request,
        );
    }

    /**
     * Rattache la requête (appelé par EorbahAPI::handle) puis recalcule la
     * plage et les validateurs.
     */
    public function withRequest(Request $request): static
    {
        $this->request = $request;
        $this->applyRequest();

        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $header => $value) {
            if (strcasecmp((string) $header, $name) === 0) {
                return (string) $value;
            }
        }

        return null;
    }

    public function path(): ?string
    {
        return $this->path;
    }

    public function size(): ?int
    {
        return $this->size;
    }

    public function etag(): ?string
    {
        return $this->etag;
    }

    public function lastModified(): ?int
    {
        return $this->lastModified;
    }

    public function contentType(): ?string
    {
        return $this->contentType;
    }

    /** Position du premier octet servi. */
    public function offset(): int
    {
        return $this->offset;
    }

    /** Nombre d'octets servis (null = jusqu'à la fin). */
    public function length(): ?int
    {
        return $this->length;
    }

    /** La plage demandée est-elle invalide ? (réponse 416) */
    public function isRangeUnsatisfiable(): bool
    {
        return $this->rangeUnsatisfiable;
    }

    public function isHeadRequest(): bool
    {
        return $this->request !== null && strtoupper($this->request->method()) === 'HEAD';
    }

    /**
     * Contenu (ou tranche demandée) sous forme de chaîne.
     *
     * Pratique pour les tests et les petits fichiers ; pour les gros fichiers,
     * laissez `send()` les diffuser par morceaux.
     */
    public function contents(?int $offset = null, ?int $length = null): string
    {
        if ($this->rangeUnsatisfiable || in_array($this->status, [204, 304], true)) {
            return '';
        }

        $offset ??= $this->offset;
        $length ??= $this->length;

        $stream = $this->openStream();

        if (!is_resource($stream)) {
            return '';
        }

        $owned = $this->path !== null;
        $contents = '';

        try {
            if ($offset > 0) {
                @fseek($stream, $offset);
            }

            $remaining = $length;

            while ($remaining === null || $remaining > 0) {
                $read = $remaining === null ? self::CHUNK_SIZE : min(self::CHUNK_SIZE, $remaining);
                $chunk = fread($stream, $read);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $contents .= $chunk;
                $remaining = $remaining === null ? null : $remaining - strlen($chunk);
            }
        } finally {
            if ($owned) {
                @fclose($stream);
            }
        }

        return $contents;
    }

    /**
     * Diffuse le corps par morceaux (utilisé par `send()` et les adaptateurs).
     *
     * @return \Generator<int, string>
     */
    public function streamChunks(): \Generator
    {
        $stream = $this->openStream();

        if (!is_resource($stream)) {
            return;
        }

        $owned = $this->path !== null;

        try {
            if ($this->offset > 0) {
                @fseek($stream, $this->offset);
            }

            $remaining = $this->length;

            while ($remaining === null || $remaining > 0) {
                $read = $remaining === null ? self::CHUNK_SIZE : min(self::CHUNK_SIZE, $remaining);
                $chunk = fread($stream, $read);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $remaining = $remaining === null ? null : $remaining - strlen($chunk);

                yield $chunk;
            }
        } finally {
            if ($owned) {
                @fclose($stream);
            }
        }
    }

    public function send(): void
    {
        if ($this->request === null && PHP_SAPI !== 'cli') {
            $this->withRequest(new Request());
        }

        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        if ($this->isHeadRequest() || in_array($this->status, [204, 304, 416], true)) {
            return;
        }

        foreach ($this->streamChunks() as $chunk) {
            echo $chunk;

            if (ob_get_level() > 0) {
                @ob_flush();
            }

            flush();
        }
    }

    // ------------------------------------------------------------------
    // Interne
    // ------------------------------------------------------------------

    /**
     * @param string|UploadFile|resource $file
     */
    private function loadSource(mixed $file): void
    {
        if (is_string($file)) {
            if (!is_file($file) || !is_readable($file)) {
                throw new EorbahAPIError("Le fichier [$file] est introuvable ou illisible.");
            }

            $this->path = $file;
            $this->size = (int) filesize($file);
            $this->lastModified = (int) filemtime($file);
            $this->etag = sprintf('"%x-%x"', $this->lastModified, $this->size);
            $this->contentType = $this->detectMimeType($file);
            $this->downloadName ??= basename($file);

            return;
        }

        if ($file instanceof UploadFile) {
            $this->stream = $file->file;
            $this->contentType = $file->content_type ?: null;
            $this->downloadName = $file->filename;
            $this->inspectStream();

            return;
        }

        if (is_resource($file)) {
            $this->stream = $file;
            $this->inspectStream();

            return;
        }

        throw new EorbahAPIError('FileResponse attend un chemin, un UploadFile ou un flux.');
    }

    private function inspectStream(): void
    {
        if (!is_resource($this->stream)) {
            return;
        }

        $stat = fstat($this->stream);

        if (is_array($stat)) {
            $this->size = isset($stat['size']) ? (int) $stat['size'] : null;
            $this->lastModified = isset($stat['mtime']) ? (int) $stat['mtime'] : null;
        }

        if ($this->size !== null) {
            $this->etag = sprintf('"stream-%x-%x"', $this->lastModified ?? 0, $this->size);
        }
    }

    private function detectMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension !== '' && isset(self::MIME_TYPES[$extension])) {
            return self::MIME_TYPES[$extension];
        }

        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);

            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        return trim(preg_replace('/[\x00-\x1F\x7F"\\\\\/]+/', '', $filename) ?? 'fichier');
    }

    private function applyMetadataHeaders(): void
    {
        if ($this->contentType !== null) {
            $this->headers['Content-Type'] ??= $this->contentType;
        }

        if ($this->size !== null) {
            $this->headers['Content-Length'] ??= (string) $this->size;
        }

        if ($this->etag !== null) {
            $this->headers['ETag'] ??= $this->etag;
        }

        if ($this->lastModified !== null) {
            $this->headers['Last-Modified'] ??= gmdate('D, d M Y H:i:s \G\M\T', $this->lastModified);
        }

        $this->headers['Accept-Ranges'] ??= 'bytes';

        if ($this->downloadName !== null) {
            $disposition = $this->disposition . '; filename="' . $this->downloadName . '"';

            if (preg_match('/[^\x20-\x7E]/', $this->downloadName) === 1) {
                $disposition .= "; filename*=UTF-8''" . rawurlencode($this->downloadName);
            }

            $this->headers['Content-Disposition'] ??= $disposition;
        }
    }

    private function applyRequest(): void
    {
        $this->status = $this->baseStatus;
        $this->offset = 0;
        $this->length = $this->size;
        $this->rangeUnsatisfiable = false;

        $this->removeInternalHeader('Content-Range');

        if ($this->size !== null) {
            $this->headers['Content-Length'] = (string) $this->size;
        }

        if ($this->request === null) {
            return;
        }

        $method = strtoupper($this->request->method());

        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }

        if ($this->baseStatus !== 200) {
            return;
        }

        if ($this->isNotModified()) {
            $this->status = 304;
            $this->removeInternalHeader('Content-Length');
            $this->removeInternalHeader('Content-Range');

            return;
        }

        $range = $this->request->getHeader('Range');

        if (is_string($range) && $range !== '') {
            $this->applyRange($range);
        }
    }

    private function isNotModified(): bool
    {
        if ($this->request === null) {
            return false;
        }

        $ifNoneMatch = $this->request->getHeader('If-None-Match');

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '' && $this->etag !== null) {
            $candidates = array_map('trim', explode(',', $ifNoneMatch));

            return in_array('*', $candidates, true) || in_array($this->etag, $candidates, true);
        }

        $ifModifiedSince = $this->request->getHeader('If-Modified-Since');

        if (is_string($ifModifiedSince) && $ifModifiedSince !== '' && $this->lastModified !== null) {
            $timestamp = strtotime($ifModifiedSince);

            return $timestamp !== false && $this->lastModified <= $timestamp;
        }

        return false;
    }

    private function applyRange(string $range): void
    {
        if ($this->size === null) {
            return;
        }

        if (preg_match('/^bytes=(\d*)-(\d*)$/i', trim($range), $matches) !== 1) {
            return; // plages multiples ou syntaxe non gérée : réponse complète
        }

        $start = $matches[1] === '' ? null : (int) $matches[1];
        $end = $matches[2] === '' ? null : (int) $matches[2];

        if ($start === null && $end === null) {
            return;
        }

        if ($start === null) {
            // Suffixe : bytes=-500
            $suffix = (int) $end;

            if ($suffix <= 0 || $this->size === 0) {
                $this->unsatisfiable();

                return;
            }

            $start = max(0, $this->size - $suffix);
            $end = $this->size - 1;
        } else {
            if ($start >= $this->size) {
                $this->unsatisfiable();

                return;
            }

            $end = $end === null ? $this->size - 1 : min($end, $this->size - 1);

            if ($end < $start) {
                $this->unsatisfiable();

                return;
            }
        }

        $length = $end - $start + 1;

        $this->offset = $start;
        $this->length = $length;
        $this->status = 206;

        $this->headers['Content-Range'] = "bytes $start-$end/" . $this->size;
        $this->headers['Content-Length'] = (string) $length;
    }

    private function unsatisfiable(): void
    {
        $this->rangeUnsatisfiable = true;
        $this->status = 416;
        $this->length = 0;

        $this->headers['Content-Range'] = 'bytes */' . ($this->size ?? 0);
        $this->headers['Content-Length'] = '0';
    }

    private function removeInternalHeader(string $name): void
    {
        unset($this->headers[$name]);
    }

    private function openStream(): mixed
    {
        if ($this->stream !== null) {
            return $this->stream;
        }

        if ($this->path !== null) {
            return @fopen($this->path, 'rb');
        }

        return null;
    }
}
