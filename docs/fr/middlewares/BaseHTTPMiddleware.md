# BaseHTTPMiddleware

La classe `Eorbahapi\Middlewares\BaseHTTPMiddleware` sert de base pour écrire des middlewares plus structurés.

## Signature

```php
class BaseHTTPMiddleware {
    public function __construct() {}
    public function process($request, $response, $next) {
        return $next();
    }
}
```

## Exemple

```php
use Eorbahapi\Middlewares\BaseHTTPMiddleware;

class LoggingMiddleware extends BaseHTTPMiddleware {
    public function process($request, $response, $next) {
        error_log('Request: ' . $request->path());
        return $next();
    }
}
```

Le point clé est de retourner le résultat de `$next()` lorsque le middleware laisse passer la requête, ou `false` s’il veut l’interrompre.
