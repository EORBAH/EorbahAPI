# TokenExpiredError

Exception levée quand un JWT a expiré.

## Description

`TokenExpiredError` est lancée lors de la validation d'un JWT dont la claim `exp` (expiration) est dans le passé.

## Utilisation

```php
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\TokenExpiredError;

$app->get('/protected', function (JWT $jwt) {
    try {
        $payload = $jwt->decode();
        return ['user' => $payload];
    } catch (TokenExpiredError $e) {
        return ['error' => 'Token has expired', 'status' => 401];
    }
});
```

## Propriétés

- `getMessage()` : Message d'erreur
- `getCode()` : Code d'erreur
- `expiredAt` : Timestamp d'expiration

## Gestion

```php
try {
    $payload = $jwt->decode();
} catch (TokenExpiredError $e) {
    // Proposer un refresh token
    return [
        'error' => 'Token expired',
        'message' => 'Please use refresh endpoint',
        'status' => 401
    ];
}
```