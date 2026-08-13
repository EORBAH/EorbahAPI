# Sécurité

Le module de sécurité de EorbahAPI propose des utilitaires pour :

- l’authentification HTTP Basic / Bearer
- les clés API dans les headers, cookies ou query string
- la génération et validation de JWT
- la limitation de débit par clé IP ou identifiant métier

## 1. HTTPAuthorizationCredentials

Cette classe sert à encapsuler un schéma d’authentification comme `Bearer`, `Basic` ou `Token`.

```php
use Eorbahapi\Security\HTTPAuthorizationCredentials;

$credentials = new HTTPAuthorizationCredentials('Bearer', 'abc123');

echo $credentials->getScheme();
```

## 2. HTTPBasic

```php
use Eorbahapi\Security\HTTPBasic;

$basic = new HTTPBasic(autoError: true, realm: 'Restricted');
$credentials = $basic();
```

Le `__invoke()` lit l’en-tête `Authorization`, vérifie le format `Basic ...` puis retourne un objet `HTTPAuthorizationCredentials`.

## 3. HTTPBearer

```php
use Eorbahapi\Security\HTTPBearer;

$bearer = new HTTPBearer(autoError: true, scheme: 'Bearer');
$credentials = $bearer();
```

## 4. RateLimiter

```php
use Eorbahapi\Security\RateLimiter;

$limiter = new RateLimiter([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

$isAllowed = $limiter->checkRateLimit('client:127.0.0.1', 60, 60);
```

## 5. JWT

```php
use Eorbahapi\Security\JWTAuth\JWT;

$jwt = new JWT('super-secret');
$token = $jwt->sign(['sub' => '123', 'role' => 'admin']);
$payload = $jwt->verify($token);
```

## 6. APIKeyHeader

```php
use Eorbahapi\Security\APIKey\APIKeyHeader;

$validator = new APIKeyHeader('X-API-Key');
$key = $validator();
```

Cette classe valide la présence d’un header et peut aussi vérifier la valeur avec `validate()` ou `validateWithCallback()`.
