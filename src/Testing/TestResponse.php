<?php

namespace Eorbahapi\Testing;

use Eorbahapi\Response;

/**
 * Réponse renvoyée par le `TestClient`.
 *
 * Enveloppe la `Response` produite par l'application et offre des accesseurs
 * pratiques (statut, en-têtes, corps, JSON) ainsi que quelques assertions
 * utilisables hors de PHPUnit.
 */
final class TestResponse
{
    public function __construct(
        private readonly Response $response,
    ) {}

    public function status(): int
    {
        return $this->response->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->response->headers;
    }

    /**
     * En-tête par nom (insensible à la casse), ou tous les en-têtes si $name
     * est omis.
     */
    public function header(?string $name = null): mixed
    {
        if ($name === null) {
            return $this->headers();
        }

        foreach ($this->headers() as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function body(): string
    {
        $body = $this->response->body;

        if (is_array($body)) {
            return (string) json_encode($body);
        }

        return (string) $body;
    }

    public function text(): string
    {
        return $this->body();
    }

    /**
     * Corps décodé depuis le JSON (null si le corps est vide).
     */
    public function json(): mixed
    {
        $body = $this->body();

        if (trim($body) === '') {
            return null;
        }

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    public function isSuccess(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function isRedirect(): bool
    {
        return in_array($this->status(), [301, 302, 303, 307, 308], true);
    }

    /**
     * Assertion de statut, utilisable hors PHPUnit.
     */
    public function assertStatus(int $expected): self
    {
        $actual = $this->status();

        if ($actual !== $expected) {
            throw new \RuntimeException(sprintf(
                "Statut attendu %d, reçu %d. Corps : %s",
                $expected,
                $actual,
                $this->body()
            ));
        }

        return $this;
    }

    /**
     * Assertion : le corps contient-il la chaîne donnée ?
     */
    public function assertSee(string $needle): self
    {
        if (!str_contains($this->body(), $needle)) {
            throw new \RuntimeException(sprintf(
                "Le corps ne contient pas '%s'. Corps : %s",
                $needle,
                $this->body()
            ));
        }

        return $this;
    }

    /**
     * La `Response` d'origine (envoi, en-têtes bruts...).
     */
    public function raw(): Response
    {
        return $this->response;
    }
}
