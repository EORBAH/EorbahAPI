# AuthMiddleware

`AuthMiddleware` est un exemple de middleware de protection d’accès. Son comportement est volontairement simple et dépend de la logique de votre application.

## Exemple

```php
use Eorbahapi\Middlewares\AuthMiddleware;

$app->addMiddleware(AuthMiddleware::class);

$app->get('/secure', function () {
    return ['ok' => true];
});
```

Dans le code actuel, le middleware n’applique pas de logique concrète ; il sert surtout de point d’extension pour sécuriser une route ou un groupe de routes.

## Pattern recommandé

```php
class CustomAuthMiddleware {
    public function process($request, $response, $next) {
        $token = $request->getBearerToken();
        if ($token === null) {
            $response->status(401);
            return false;
        }

        return $next();
    }
}
```
