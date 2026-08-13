# HTTPSRedirectMiddleware

`HTTPSRedirectMiddleware` est un middleware de redirection forcée vers HTTPS.

## Structure attendue

```php
use Eorbahapi\Middlewares\HTTPSRedirectMiddleware;

$app->addMiddleware(HTTPSRedirectMiddleware::class);
```

Dans sa version actuelle, il s’agit d’un squelette minimal, donc il faut compléter le comportement en fonction de votre environnement de production.

## Exemple de logique

```php
class EnforceHTTPS extends \\Eorbahapi\\Middlewares\\BaseHTTPMiddleware {
    public function process($request, $response, $next) {
        if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            $response->status(301);
            return false;
        }

        return $next();
    }
}
```
