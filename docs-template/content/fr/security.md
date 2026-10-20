# Sécurité

## Ce que vous allez apprendre

Protéger un paramètre de handler avec `#[Security]` : schémas HTTP Bearer et
Basic, clés d'API, OAuth2, jetons JWT — et le comportement exact en cas
d'authentification manquante.

## Le code

`docs_src/SecurityExample.php` :

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

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /bearer` + `Authorization: Bearer mon-jeton` | 200 | `{"scheme":"Bearer","token":"mon-jeton"}` |
| `GET /bearer` sans en-tête | 401 (`WWW-Authenticate: Bearer`) | `{"error":true,"status":401,"message":"Authorization header missing"}` |
| `GET /basic` + `Authorization: Basic b3VtYXI6c2VjcmV0` | 200 | `{"credentials":"oumar:secret"}` |
| `GET /api-key?api_key=secret` | 200 | `{"key":"secret"}` |
| `GET /api-key` | 403 | `{"error":true,"status":403,"message":"API key query parameter missing"}` |

### Ce que fait `#[Security]`

`Eorbahapi\Params\Security` désigne un schéma de sécurité dans son premier
argument. Le schéma est exécuté, et sa valeur de retour est injectée dans le
paramètre : un `HTTPBearer` produit un `HTTPAuthorizationCredentials`, un
`APIKeyQuery` produit la chaîne lue dans la requête. Si l'argument est omis, la
classe déclarée dans le type du paramètre sert de schéma. Les schémas reçoivent
la `Request` courante, jamais les superglobales : ils restent testables avec
`TestClient`.

`scopes` est conservé comme métadonnée OAuth2 : il documente les portées
demandées, mais aucun contrôle automatique n'est effectué — la vérification des
portées appartient au schéma lui-même.

### Les schémas livrés

Tous vivent sous `Eorbahapi\Security\` ; la colonne « valeur injectée » indique
ce que reçoit le paramètre du handler.

| Schéma | Valeur injectée | Échec |
| --- | --- | --- |
| `HTTP\HTTPBearer` | `HTTPAuthorizationCredentials` | 401 + `WWW-Authenticate` |
| `HTTP\HTTPBasic` | `HTTPAuthorizationCredentials` (identifiants décodés) | 401 + `WWW-Authenticate` |
| `APIKey\APIKeyQuery` | la clé (paramètre `api_key` par défaut) | 403 |
| `APIKey\APIKeyHeader` | la clé (en-tête `X-API-Key` par défaut) | 403 |
| `APIKey\APIKeyCookie` | la clé (cookie `api_key` par défaut) | 403 |
| `OAuth2\OAuth2` | le jeton brut de l'en-tête `Bearer` | 401 + `WWW-Authenticate` |
| `OAuth2\OAuth2PasswordBearer` | l'instance du schéma (`resolve()` garde requête et réponse) | à votre charge (`validatePasswordGrant()`) |

`HTTPAuthorizationCredentials` expose `getScheme()`, `getCredentials()`,
`isBearer()`, `isBasic()`, `isScheme($nom)` et `toArray()` ; `HTTPBasic` fournit
en plus `encodeCredentials()` et `decodeCredentials()`.

### Les jetons JWT

`Eorbahapi\Security\JWTAuth\JWT` (basé sur `firebase/php-jwt`) est construit avec
un secret et un algorithme (`HS256` par défaut) : `sign($payload, $secret, $options)`
accepte `['expiresIn' => '1h']`, `verify($token)` retourne le payload et lève
`JsonWebTokenError` / `TokenExpiredError` / `NotBeforeError`, `decode($token)` lit
le payload sans vérifier la signature, `isExpired($token)` répond sans vérifier la
signature non plus, et `tokenPair($userData, $accessExpiresIn, $refreshExpiresIn)`
génère la paire `access_token` / `refresh_token`.

:::callout{type="warning" title="Clés d'API : 403, pas 401"}
Un schéma de clé d'API lève une `HTTPException` 403 et n'ajoute pas
d'en-tête `WWW-Authenticate` : le client n'a pas de mécanisme
d'authentification à renégocier. Les schémas HTTP (Bearer, Basic, OAuth2)
lèvent un 401 avec le défi correspondant.
:::

:::callout{type="success" title="Désactiver le comportement automatique"}
Chaque schéma accepte `autoError: false` : au lieu de lever l'exception, il
laisse votre code décider. Le constructeur permet aussi de renommer la clé
(`new APIKeyHeader('X-Token')`) ou de fixer le `realm` d'un `HTTPBasic`.
:::

## Récapitulatif

- `#[Security(Schema::class)]` exécute le schéma et injecte sa valeur ;
- `HTTPBearer` et `HTTPBasic` injectent un `HTTPAuthorizationCredentials` ;
- les schémas de clé d'API lisent `APIKeyQuery`, `APIKeyHeader` ou `APIKeyCookie` ;
- `OAuth2PasswordBearer` s'appuie sur `JWTAuth\JWT` pour signer et vérifier les jetons ;
- l'échec produit un 401 avec `WWW-Authenticate` (Bearer, Basic) ou un 403 (clé d'API) ;
- `scopes` reste une métadonnée : la logique de portée appartient au schéma.

[Suivant : Middlewares](#/fr/middlewares)
