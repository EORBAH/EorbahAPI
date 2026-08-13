# NotBeforeError

Exception levée quand un JWT n'est pas encore valide.

## Description

`NotBeforeError` est lancée lors de la validation d'un JWT dont la claim `nbf` (Not Before) est dans le futur.

## Utilisation

```php
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\NotBeforeError;

$app->get('/protected', function (JWT $jwt) {
    try {
        $payload = $jwt->decode();
        return ['user' => $payload];
    } catch (NotBeforeError $e) {
        return ['error' => 'Token not yet valid', 'status' => 401];
    }
});
```

## Cas d'usage

Utile quand vous voulez générer des tokens qui ne seront valides qu'à partir d'une certaine date/heure:

```php
// Générer un token valide dans 2 heures
$payload = [
    'sub' => $user['id'],
    'nbf' => time() + 7200,  // Not Before: in 2 hours
    'exp' => time() + 86400  // Expires: in 24 hours
];

$token = $jwt->encode($payload);
// Ce token ne pourra pas être utilisé avant 2 heures
```

## Gestion

```php
try {
    $payload = $jwt->decode();
} catch (NotBeforeError $e) {
    // Token n'est pas encore valide
    return [
        'error' => 'Token not yet valid',
        'message' => 'Please wait before using this token',
        'status' => 400
    ];
}
```

## Propriétés

- `getMessage()` : Message d'erreur
- `getCode()` : Code d'erreur
- Hérite de `JsonWebTokenError`
