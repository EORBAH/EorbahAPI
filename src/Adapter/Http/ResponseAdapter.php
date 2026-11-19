<?php

namespace Eorbahapi\Adapter\Http;

use Eorbahapi\Contracts\ResponseInterface;
use Eorbahapi\Response;

/**
 * Adapte une `Eorbahapi\Response` au contrat `ResponseInterface`.
 *
 * Les en-têtes sont modifiés dans le tableau de la réponse (pas d'appel à la
 * fonction `header()`), ce qui rend l'adaptateur utilisable hors requête HTTP
 * réelle : tests, prévisualisation, runners asynchrones.
 */
final class ResponseAdapter implements ResponseInterface
{
    public function __construct(private readonly Response $response)
    {
    }

    public static function create(?Response $response = null): self
    {
        return new self($response ?? new Response());
    }

    public static function from(Response $response): self
    {
        return new self($response);
    }

    public function unwrap(): Response
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->status;
    }

    public function setStatus(int $status): static
    {
        $this->response->status = $status;

        return $this;
    }

    public function body(): string|array|null
    {
        return $this->response->body;
    }

    public function setBody(string|array|null $body): static
    {
        $this->response->body = $body;

        return $this;
    }

    public function headers(): array
    {
        return $this->response->headers;
    }

    public function header(string $name): ?string
    {
        $headers = array_change_key_case($this->response->headers, CASE_LOWER);

        return $headers[strtolower($name)] ?? null;
    }

    public function setHeader(string $name, string $value): static
    {
        $this->response->headers[$name] = $value;

        return $this;
    }

    public function removeHeader(string $name): void
    {
        unset($this->response->headers[$name]);
    }

    public function text(): string
    {
        return $this->response->text();
    }

    public function send(): void
    {
        $this->response->send();
    }
}
