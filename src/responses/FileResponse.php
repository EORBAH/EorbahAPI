<?php

namespace Eorbah545\Eorbahapi\responses;

class FileResponse {

}

/*

public function FileResponse(string $filePath, array $options = []): void
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->status(404)->send("File not found");
            return;
        }

        $fileInfo = pathinfo($filePath);
        $extension = strtolower($fileInfo['extension'] ?? '');
        $contentTypeMap = [
            'json' => 'json',
            'html' => 'html',
            'jpg' => 'image',
            'jpeg' => 'image',
            'png' => 'png',
            'gif' => 'gif',
            'css' => 'css',
            'js' => 'javascript',
            'pdf' => 'pdf',
            'mp4' => 'video',
            'mp3' => 'mp3',
        ];

        $contentType = $contentTypeMap[$extension] ?? 'application/octet-stream';

        $this->set_content_type($contentType, null, [
            'disposition' => $options['disposition'] ?? 'inline',
            'filename' => $options['filename'] ?? basename($filePath),
            'custom_headers' => $options['custom_headers'] ?? []
        ]);

        readfile($filePath);
    }
*/