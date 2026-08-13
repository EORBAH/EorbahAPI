<?php

namespace Eorbahapi;

class Response {
    public $headers = [];
    
    public function JSONResponse($data, $header = true) {
        if ($header) {
            header("Content-Type: application/json");
        }
        echo json_encode($data);
    }


    public function json($data, $header = true) {
        $this->JSONResponse($data, $header);
    }
 
    public function send($message = null, $type = null) {
        if (is_string($message)) {
            $message = trim($message);
        }
        
        if ($type == "json") {
            $this->JSONResponse($message);
        } elseif ($message === null) {
            echo "";
        } else {
            echo $message;
        }
    }

    public function status(int $code) {
        if (!headers_sent()) {
            http_response_code($code);
        }

        return $this;
    }

   
    function HTMLResponse(string $content, int $statusCode = 200, array $headers = []): void {
        if (headers_sent()) {
            $this->status(200)->send($content);
            return;
        }

        $this->status($statusCode)->setHeader('Content-Type', 'text/html; charset=utf-8');

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        $this->send($content);
    }

    public function FileResponse(string $filePath, array $options = []): void {
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

    public function StreamingResponse($gen, $ContentType = 'text/event-stream'): void {
        $this->setHeader("Content-Type", $ContentType);
        $this->setHeader('Cache-Control', 'no-cache');
    
        foreach ($gen as $event) {
            echo $event;
            ob_flush();
            flush();
        }
    }

    public function set_content_type(
        string $contentType = 'html',
        ?int $cacheMaxAge = null,
        array $options = []
    ): void {
        $mimeTypes = [
            'json' => 'application/json',
            'manifest' => 'application/manifest+json',
            'html' => 'text/html',
            'image' => 'image/jpeg',
            'javascript' => 'application/javascript',
            'css' => 'text/css',
            'woff2' => 'application/font',
            'text' => 'text/plain',
            'video' => 'video/mp4',
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg'
        ];

        $mime = $mimeTypes[$contentType] ?? $contentType;
        $charset = $options['charset'] ?? 'UTF-8';

        if (in_array($contentType, ['html', 'json', 'text', 'css', 'javascript', 'xml'])) {
            $mime .= "; charset=$charset";
        }

        header("Content-Type: $mime");

        if ($cacheMaxAge !== null) {
            header('Cache-Control: public, max-age=' . $cacheMaxAge);
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheMaxAge) . ' GMT');
        } else {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        if (isset($options['disposition'])) {
            $disposition = $options['disposition'] === 'attachment' && isset($options['filename'])
                ? "attachment; filename=\"{$options['filename']}\""
                : $options['disposition'];
            header("Content-Disposition: $disposition");
        }

        if (!empty($options['custom_headers']) && is_array($options['custom_headers'])) {
            foreach ($options['custom_headers'] as $name => $value) {
                header("$name: $value");
            }
        }
    }

    public function RedirectResponse(string $url, int $statusCode = 302): void {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    public function redirect(string $url, int $statusCode = 302): void {
        $this->RedirectResponse($url, $statusCode);
    }

    public function setHeader($name, $value) {
        header("$name: $value");
    }

    public function removeHeader($name) {
        header_remove($name);
    }

    public function cookie(string $name, $value, array $options = []): void {
        $defaultOptions = [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ];

        $options = array_merge($defaultOptions, $options);

        setcookie(
            $name,
            $value,
            [
                'expires' => $options['expires'],
                'path' => $options['path'],
                'domain' => $options['domain'],
                'secure' => $options['secure'],
                'httponly' => $options['httponly'],
                'samesite' => $options['samesite']
            ]
        );

        $_COOKIE[$name] = $name;
    }

    public function clearCookie($name) {
        if (isset($_COOKIE[$name])) {
            setcookie(
                $name, '',
                [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'domain' => '',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
            unset($_COOKIE[$name]);
        }
    }
}
