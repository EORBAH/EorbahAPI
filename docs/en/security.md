# Security

## What you will learn

Protect a handler parameter with `#[Security]`: HTTP Bearer and Basic schemes, API keys, OAuth2, JWT tokens — and the exact behavior when authentication is missing.

## The code

`docs_src/SecurityExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Security;
use Eorbahapi\Security\APIKey\APIKeyQuery;
use Eorbahapi\Security\HTTP\HTTPAuthorizationCredentials;
use Eorbahapi\Security\HTTP\HTTPBasic;
use Eorbahapi\Security\HTTP\HTTPBearer;

/**
 * Sécurité : schémas HTTP Bearer, Basic et clé d'API.
 *
 * `#[Security]` exécute le schéma désigné et injecte sa valeur ; une
 * authentification manquante lève une `HTTPException` 401 ou 403, convertie en
 * réponse JSON par les gestionnaires d'exceptions par défaut.
 */
final class SecurityExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Bearer : le jeton est exposé via HTTPAuthorizationCredentials.
        $app->get('/bearer', function (#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials) {
            return ['scheme' => $credentials->getScheme(), 'token' => $credentials->getCredentials()];
        });

        // Basic : les identifiants sont décodés (username:password).
        $app->get('/basic', function (#[Security(HTTPBasic::class)] HTTPAuthorizationCredentials $credentials) {
            return ['credentials' => $credentials->getCredentials()];
        });

        // Clé d'API passée en paramètre de requête.
        $app->get('/api-key', function (#[Security(APIKeyQuery::class)] string $key) {
            return ['key' => $key];
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/bearer', [
                'headers' => ['Authorization' => 'Bearer mon-jeton'],
            ], json: ['scheme' => 'Bearer', 'token' => 'mon-jeton']),

            self::scenario('GET', '/bearer', [], status: 401, headers: ['WWW-Authenticate' => 'Bearer']),

            self::scenario('GET', '/basic', [
                'headers' => ['Authorization' => 'Basic ' . base64_encode('oumar:secret')],
            ], json: ['credentials' => 'oumar:secret']),

            self::scenario('GET', '/api-key?api_key=secret', json: ['key' => 'secret']),

            self::scenario('GET', '/api-key', status: 403),
        ];
    }
}
```

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `GET /bearer` + `Authorization: Bearer mon-jeton` | 200 | `{"scheme":"Bearer","token":"mon-jeton"}` |
| `GET /bearer` without header | 401 (`WWW-Authenticate: Bearer`) | `{"error":true,"status":401,"message":"Authorization header missing"}` |
| `GET /basic` + `Authorization: Basic b3VtYXI6c2VjcmV0` | 200 | `{"credentials":"oumar:secret"}` |
| `GET /api-key?api_key=secret` | 200 | `{"key":"secret"}` |
| `GET /api-key` | 403 | `{"error":true,"status":403,"message":"API key query parameter missing"}` |

### What `#[Security]` does

`Eorbahapi\Params\Security` designates a security scheme in its first argument. The scheme is executed, and its return value is injected into the parameter: an `HTTPBearer` produces an `HTTPAuthorizationCredentials`, an `APIKeyQuery` produces the string read from the request. If the argument is omitted, the class declared in the parameter's type serves as the scheme. Schemes receive the current `Request`, never the superglobals: they remain testable with `TestClient`.

`scopes` is kept as OAuth2 metadata: it documents the requested scopes, but no automatic check is performed — scope verification belongs to the scheme itself.

### The built-in schemes

All of them live under `Eorbahapi\Security\`; the "injected value" column indicates what the handler parameter receives.

| Scheme | Injected value | Failure |
| --- | --- | --- |
| `HTTP\HTTPBearer` | `HTTPAuthorizationCredentials` | 401 + `WWW-Authenticate` |
| `HTTP\HTTPBasic` | `HTTPAuthorizationCredentials` (decoded credentials) | 401 + `WWW-Authenticate` |
| `APIKey\APIKeyQuery` | the key (`api_key` parameter by default) | 403 |
| `APIKey\APIKeyHeader` | the key (`X-API-Key` header by default) | 403 |
| `APIKey\APIKeyCookie` | the key (`api_key` cookie by default) | 403 |
| `OAuth2\OAuth2` | the raw token from the `Bearer` header | 401 + `WWW-Authenticate` |
| `OAuth2\OAuth2PasswordBearer` | the scheme instance (`resolve()` keeps request and response) | up to you (`validatePasswordGrant()`) |

`HTTPAuthorizationCredentials` exposes `getScheme()`, `getCredentials()`, `isBearer()`, `isBasic()`, `isScheme($nom)` and `toArray()`; `HTTPBasic` additionally provides `encodeCredentials()` and `decodeCredentials()`.

### JWT tokens

`Eorbahapi\Security\JWTAuth\JWT` (based on `firebase/php-jwt`) is built with a secret and an algorithm (`HS256` by default): `sign($payload, $secret, $options)` accepts `['expiresIn' => '1h']`, `verify($token)` returns the payload and raises `JsonWebTokenError` / `TokenExpiredError` / `NotBeforeError`, `decode($token)` reads the payload without verifying the signature, `isExpired($token)` answers without verifying the signature either, and `tokenPair($userData, $accessExpiresIn, $refreshExpiresIn)` generates the `access_token` / `refresh_token` pair.

!!! warning "API keys: 403, not 401"
    An API key scheme raises a 403 `HTTPException` and does not add a
    `WWW-Authenticate` header: the client has no authentication mechanism
    to renegotiate. HTTP schemes (Bearer, Basic, OAuth2) raise a 401 with
    the corresponding challenge.

!!! tip "Disabling the automatic behavior"
    Each scheme accepts `autoError: false`: instead of raising the exception, it
    lets your code decide. The constructor also lets you rename the key
    (`new APIKeyHeader('X-Token')`) or set the `realm` of an `HTTPBasic`.

## Summary

- `#[Security(Schema::class)]` executes the scheme and injects its value;
- `HTTPBearer` and `HTTPBasic` inject an `HTTPAuthorizationCredentials`;
- API key schemes read `APIKeyQuery`, `APIKeyHeader` or `APIKeyCookie`;
- `OAuth2PasswordBearer` relies on `JWTAuth\JWT` to sign and verify tokens;
- failure produces a 401 with `WWW-Authenticate` (Bearer, Basic) or a 403 (API key);
- `scopes` remains metadata: scope logic belongs to the scheme.

[Next: Middlewares](middlewares.md){ .md-button }
