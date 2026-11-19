<?php

namespace Eorbahapi\Adapter\Http;

use Eorbahapi\Contracts\RequestInterface;
use Eorbahapi\Request;

/**
 * Adapte une `Eorbahapi\Request` au contrat `RequestInterface`.
 *
 * L'adaptateur ne modifie pas la requête d'origine : les attributs ajoutés via
 * `withAttribute()` vivent dans l'adaptateur (copie superficielle). Les
 * serveurs et runners peuvent ainsi manipuler une requête stable, tandis que
 * `EorbahAPI::handle()` continue de recevoir la requête concrète via
 * `unwrap()`.
 */
final class RequestAdapter implements RequestInterface
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    public function __construct(private readonly Request $request)
    {
    }

    public static function fromGlobals(): self
    {
        return new self(new Request());
    }

    /**
     * Construit l'adaptateur autour d'une requête concrète (raccourci).
     */
    public static function create(Request $request): self
    {
        return new self($request);
    }

    public function unwrap(): Request
    {
        return $this->request;
    }

    public function getMethod(): string
    {
        return $this->request->method();
    }

    public function getUri(): string
    {
        return $this->request->uri();
    }

    public function getPath(): string
    {
        return $this->request->path();
    }

    public function getQueryParams(): array
    {
        $query = $this->request->query();

        return is_array($query) ? $query : [];
    }

    public function getHeaders(): array
    {
        $headers = $this->request->getHeader();

        return is_array($headers) ? $headers : [];
    }

    public function getHeader(string $name): ?string
    {
        $value = $this->request->getHeader($name);

        return $value === null ? null : (string) $value;
    }

    public function getBody(): string|array|null
    {
        $body = $this->request->body();

        return is_array($body) || is_string($body) ? $body : null;
    }

    public function getPathParams(): array
    {
        $params = $this->request->params();

        return is_array($params) ? $params : [];
    }

    public function getServerParams(): array
    {
        $server = $this->request->server();

        return is_array($server) ? $server : [];
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): static
    {
        $clone = clone $this;
        $clone->attributes[$name] = $value;

        return $clone;
    }
}
