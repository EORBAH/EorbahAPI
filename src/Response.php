<?php

namespace Eorbahapi;

class Response {
    public $headers = [];

    public function status(int $code) {
        if (!headers_sent()) {
            http_response_code($code);
        }

        return $this;
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

    public function setHeader($name, $value) {
        header("$name: $value");
        return $this;
    }

    public function header($name, $value) {
        return $this->setHeader($name, $value);
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
