<?php

namespace Eorbahapi;

class Response
{
    /**
     * Cookies posés sur la réponse : utilisés par les adaptateurs de serveur
     * (Swoole, tests) qui ne passent pas par `setcookie()`.
     *
     * @var list<array{name: string, value: string, options: array<string, mixed>}>
     */
    public array $cookies = [];

    public function __construct(
        public string|array|null $body = null,
        public int $status = 200,
        public array $headers = [],
    ) {}

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        echo $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string|array|null
    {
        return $this->body;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    /**
     * Corps sous forme de chaîne (les tableaux sont encodés en JSON).
     */
    public function text(): string
    {
        return is_array($this->body) ? (string) json_encode($this->body) : (string) $this->body;
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

        $this->headers['Content-Type'] = $mime;

        if ($cacheMaxAge !== null) {
            $this->headers['Cache-Control'] = "public, max-age=$cacheMaxAge";
            $this->headers['Expires'] = gmdate('D, d M Y H:i:s', time() + $cacheMaxAge) . ' GMT';
        } else {
            $this->headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0';
            $this->headers['Pragma'] = 'no-cache';
        }

        if (isset($options['disposition'])) {
            $disposition = $options['disposition'] === 'attachment' && isset($options['filename'])
                ? "attachment; filename=\"{$options['filename']}\""
                : $options['disposition'];
            $this->headers['Content-Disposition'] = $disposition;
        }

        if (!empty($options['custom_headers']) && is_array($options['custom_headers'])) {
            foreach ($options['custom_headers'] as $name => $value) {
                $this->headers[$name] = $value;
            }
        }
    }

    public function setHeader(string $name, string $value)
    {
        header("$name: $value");
        return $this;
    }

    public function header(string $name, string $value)
    {
        return $this->setHeader($name, $value);
    }

    public function removeHeader(string $name)
    {
        header_remove($name);
    }

    public function cookie(string $name, mixed $value, array $options = []): void
    {
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

        $this->cookies[] = ['name' => $name, 'value' => (string) $value, 'options' => $options];

        $_COOKIE[$name] = $value;
    }

    public function clearCookie(string $name)
    {
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ];

        setcookie($name, '', $options);
        $this->cookies[] = ['name' => $name, 'value' => '', 'options' => $options];

        unset($_COOKIE[$name]);
    }

    /**
     * En-têtes `Set-Cookie` correspondant aux cookies posés.
     *
     * Utile aux adaptateurs de serveur qui n'exécutent pas le SAPI PHP
     * (Swoole) : ils peuvent transmettre ces en-têtes tels quels.
     *
     * @return list<string>
     */
    public function cookie_headers(): array
    {
        $headers = [];

        foreach ($this->cookies as $cookie) {
            $options = $cookie['options'] + [
                'expires' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ];

            $header = $cookie['name'] . '=' . rawurlencode($cookie['value']);

            if ((int) $options['expires'] > 0) {
                $header .= '; Expires=' . gmdate('D, d M Y H:i:s \G\M\T', (int) $options['expires']);
                $header .= '; Max-Age=' . max(0, (int) $options['expires'] - time());
            }

            if ((string) $options['path'] !== '') {
                $header .= '; Path=' . $options['path'];
            }

            if ((string) $options['domain'] !== '') {
                $header .= '; Domain=' . $options['domain'];
            }

            if ($options['secure']) {
                $header .= '; Secure';
            }

            if ($options['httponly']) {
                $header .= '; HttpOnly';
            }

            if ((string) $options['samesite'] !== '') {
                $header .= '; SameSite=' . $options['samesite'];
            }

            $headers[] = $header;
        }

        return $headers;
    }
}
