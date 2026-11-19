<?php

namespace Eorbahapi\Openapi\Models;

use Eorbahapi\Security\APIKey\APIKeyCookie;
use Eorbahapi\Security\APIKey\APIKeyHeader;
use Eorbahapi\Security\APIKey\APIKeyQuery;
use Eorbahapi\Security\HTTP\HTTPBasic;
use Eorbahapi\Security\HTTP\HTTPBearer;

/**
 * Schémas de sécurité OpenAPI associés aux attributs `#[Security]`.
 *
 * Seuls les schémas dont la définition est entièrement déterminée par la classe
 * sont documentés (`HTTPBearer`, `HTTPBasic`, `APIKey*`). Pour un schéma
 * paramétré (OAuth2, OIDC), la définition est laissée à l'appelant via
 * `EorbahAPI::openapi(['securitySchemes' => [...]])`.
 */
final class SecuritySchemes
{
    /** @var array<string, array<string, mixed>> */
    private array $schemes = [];

    /**
     * Exigence de sécurité d'une opération, ou null si la dépendance n'est pas
     * un schéma reconnu.
     *
     * @param string|array|null $dependency Classe ou callable déclaré par #[Security].
     * @return array<string, list<string>>|null
     */
    public function requirement(string|array|null $dependency): ?array
    {
        if (!is_string($dependency)) {
            return null;
        }

        $class = ltrim($dependency, '\\');

        if (!class_exists($class)) {
            return null;
        }

        $scheme = $this->schemeFor($class);

        if ($scheme === null) {
            return null;
        }

        [$name, $definition] = $scheme;
        $this->schemes[$name] = $definition;

        return [$name => []];
    }

    /**
     * Bloc `components` des schémas de sécurité.
     *
     * @return array<string, mixed>
     */
    public function components(): array
    {
        if ($this->schemes === []) {
            return [];
        }

        $schemes = $this->schemes;
        ksort($schemes);

        return ['securitySchemes' => $schemes];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    private function schemeFor(string $class): ?array
    {
        if (is_a($class, HTTPBearer::class, true)) {
            return ['HTTPBearer', ['type' => 'http', 'scheme' => 'bearer']];
        }

        if (is_a($class, HTTPBasic::class, true)) {
            return ['HTTPBasic', ['type' => 'http', 'scheme' => 'basic']];
        }

        if (is_a($class, APIKeyHeader::class, true)) {
            return ['APIKeyHeader', ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key']];
        }

        if (is_a($class, APIKeyQuery::class, true)) {
            return ['APIKeyQuery', ['type' => 'apiKey', 'in' => 'query', 'name' => 'api_key']];
        }

        if (is_a($class, APIKeyCookie::class, true)) {
            return ['APIKeyCookie', ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'api_key']];
        }

        return null;
    }
}
