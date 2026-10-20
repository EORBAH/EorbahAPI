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
