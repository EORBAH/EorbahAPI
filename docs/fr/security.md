# Sécurité

## Ce que vous allez apprendre

Protéger une route avec des schémas de sécurité prêts à l'emploi : Bearer,
Basic, clés d'API, OAuth2, JWT, et comprendre la documentation automatique des
schémas dans OpenAPI.

## Bearer et Basic

```php
<?php

use Eorbahapi\Params\Security;
use Eorbahapi\Security\HTTP\HTTPAuthorizationCredentials;
use Eorbahapi\Security\HTTP\HTTPBearer;

$app->get('/me', function (
    #[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials,
) {
    return [
        'scheme' => $credentials->getScheme(),
        'token' => $credentials->getCredentials(),
    ];
});
```

```console
$ curl "http://127.0.0.1:8000/me" -H "Authorization: Bearer abc.def"
{"scheme":"Bearer","token":"abc.def"}
```

Sans en-tête, la réponse est une **401** avec `WWW-Authenticate: Bearer`. Le
contrôle du jeton (validité, expiration, scopes) reste à votre charge.

`HTTPBasic` suit le même modèle (`Authorization: Basic base64(user:pass)`) et
décode les identifiants dans `getCredentials()`.

### Clés d'API

| Schéma | Défaut |
| --- | --- |
| `APIKeyHeader` | en-tête `X-API-Key` |
| `APIKeyQuery` | query `api_key` |
| `APIKeyCookie` | cookie `api_key` |

```php
<?php

use Eorbahapi\Params\Security;
use Eorbahapi\Security\APIKey\APIKeyHeader;

$app->get('/key', function (
    #[Security(APIKeyHeader::class)] string $apiKey,
) {
    return ['key' => $apiKey];
});
```

Pour un nom d'en-tête personnalisé, sous-classez le schéma (`__construct` du
schéma reçoit le nom) ou lisez l'en-tête avec `#[Header]`.

## OAuth2 et jetons

`OAuth2` fournit la base (`tokenUrl`, `scopes`, nom du schéma) ; les variantes
`OAuth2PasswordBearer`, `OAuth2AuthorizationCodeBearer` et `OpenIdConnect`
ajoutent le flux.

`OAuth2PasswordBearer` s'utilise avec un fournisseur d'utilisateurs :

```php
<?php

use Eorbahapi\Security\OAuth2\OAuth2PasswordBearer;
use Eorbahapi\Security\OAuth2\UserInterface;
use Eorbahapi\Security\OAuth2\UserProviderInterface;

final class Utilisateur implements UserInterface
{
    public function __construct(private string $id, private string $nom) {}

    public function getIdentifier(): string { return $this->id; }
    public function getUsername(): string { return $this->nom; }
}

final class Fournisseur implements UserProviderInterface
{
    public function findUserByCredentials(string $username, string $password): ?UserInterface
    {
        return $username === 'oumar' && $password === 'secret'
            ? new Utilisateur('1', 'oumar')
            : null;
    }
}

$oauth2 = new OAuth2PasswordBearer(
    userProvider: new Fournisseur(),
    jwtSecret: str_repeat('k', 32),
    tokenUrl: '/token',
);
```

Endpoint de jeton :

```php
<?php

use Eorbahapi\Params\Form;

$app->post('/token', function (
    #[Form] string $username,
    #[Form] string $password,
) use ($oauth2) {
    return $oauth2->validatePasswordGrant($username, $password);
});
// {"access_token":"…","token_type":"bearer","expires_in":3600}
```

Vérification dans une route protégée :

```php
<?php

use Eorbahapi\Params\Header;

$app->get('/profil', function (
    #[Header('Authorization')] string $authorization,
) use ($oauth2) {
    $token = str_starts_with($authorization, 'Bearer ')
        ? substr($authorization, 7)
        : '';

    $payload = $oauth2->verifyToken($token);

    return ['utilisateur' => $payload?->username];
});
```

`OAuth2AuthorizationCodeBearer($authorizationUrl, $tokenUrl, …)` documente un
flux code ; `OpenIdConnect($issuer, $clientId, …)` branche un fournisseur OIDC.

## JWT

```php
<?php

use Eorbahapi\Security\JWTAuth\JWT;

$jwt = new JWT(str_repeat('k', 32), 'HS256');

$token = $jwt->sign(['sub' => '1'], null, ['expiresIn' => 3600]);
$payload = $jwt->verify($token);   // lève TokenExpiredError si expiré
$paire = $jwt->tokenPair(['sub' => '1']); // access + refresh
```

Une clé HS256 doit faire **au moins 32 octets** (`firebase/php-jwt` v7).

## Nonce CSP

`Nonce` génère un nonce et pose l'en-tête `Content-Security-Policy` :

```php
<?php

use Eorbahapi\Security\Nonce;

$nonce = new Nonce();
// header: script-src 'self' 'nonce-…'; …
$nonce->nonce; // valeur à insérer dans les balises <script nonce="…">
```

## Documentation

Les schémas `HTTPBearer`, `HTTPBasic` et `APIKey*` sont documentés
automatiquement dans `components.securitySchemes` et chaque opération protégée
déclare son `security`. Swagger UI affiche alors le bouton **Authorize** — voir
[Documentation OpenAPI](openapi.md).

!!! warning "401 ou 403 ?"
    Un schéma qui ne trouve pas ses identifiants renvoie **401** avec le
    challenge attendu. Une fois authentifié, un refus métier est plutôt un
    **403** (`throw new HTTPException(403, …)`).

## Récapitulatif

- `#[Security(HTTPBearer::class)]` injecte les identifiants et renvoie 401 si
  l'en-tête manque ;
- `HTTPBasic` et les schémas `APIKey*` suivent le même principe ;
- OAuth2 repose sur `OAuth2PasswordBearer`/`AuthorizationCodeBearer`/
  `OpenIdConnect` ;
- `JWT` signe, vérifie et produit des paires de jetons ;
- `Nonce` gère le nonce CSP ;
- les schémas connus sont documentés automatiquement dans OpenAPI.

[Suivant : Middlewares](middlewares.md){ .md-button }
