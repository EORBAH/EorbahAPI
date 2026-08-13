# JsonWebTokenError

Exception de base pour les erreurs JWT.

## Description

`JsonWebTokenError` est l'exception de base levée lors de problèmes de validation ou de décodage d'un JWT.

## Utilisation

```php
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\JsonWebTokenError;
use Eorbahapi\Security\JWTAuth\TokenExpiredError;
use Eorbahapi\Security\JWTAuth\NotBeforeError;

$app->get('/protected', function (JWT $jwt) {
    try {
        $payload = $jwt->decode();
        return ['user' => $payload];
    } catch (TokenExpiredError $e) {
        return ['error' => 'Token expired'];
    } catch (NotBeforeError $e) {
        return ['error' => 'Token not yet valid'];
    } catch (JsonWebTokenError $e) {
        return ['error' => 'Invalid token'];
    }
});
```

## Sous-classes

- `TokenExpiredError` : Token expiré
- `NotBeforeError` : Token pas encore valide (`nbf` dans le futur)
- Autres variations selon l'implémentation

## Capture générique

```php
try {
    $payload = $jwt->decode();
} catch (JsonWebTokenError $e) {
    // Capture toutes les erreurs JWT
    logger()->error('JWT Error: ' . $e->getMessage());
    return ['error' => 'Authentication failed', 'status' => 401];
}
```

## Propriétés

- `getMessage()` : Description de l'erreur
- `getCode()` : Code d'erreur
- `getPrevious()` : Exception précédente (si enveloppée)
