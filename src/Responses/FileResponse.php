<?php

namespace Eorbahapi\Responses;

function FileResponse(string $filePath, array $options = []): string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return RedirectResponse('/404', 404);
    }

    $fileInfo = pathinfo($filePath);
    $extension = strtolower($fileInfo['extension'] ?? '');

    $contentTypeMap = [
        'json' => 'application/json',
        'html' => 'text/html; charset=UTF-8',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
        'mp3' => 'audio/mpeg',
    ];

    $contentType = $contentTypeMap[$extension] ?? 'application/octet-stream';

    if (!headers_sent()) {
        header('Content-Type: ' . $contentType);

        $disposition = $options['disposition'] ?? 'inline';
        $filename = $options['filename'] ?? basename($filePath);
        if ($disposition === 'attachment') {
            header('Content-Disposition: attachment; filename="' . $filename . '"');
        }

        foreach ($options['custom_headers'] ?? [] as $name => $value) {
            header($name . ': ' . $value, false);
        }
    }

    $contents = file_get_contents($filePath);
    if ($contents === false) {
        return '';
    }

    return $contents;
}
