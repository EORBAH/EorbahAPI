# GZipMiddleware

Le middleware `GZipMiddleware` est prévu pour compresser les réponses, mais l’implémentation actuelle est un squelette prêt à étendre.

## Exemple d’intégration

```php
use Eorbahapi\Middlewares\GZipMiddleware;

$app->addMiddleware(GZipMiddleware::class);
```

## Point de conception

Le mécanisme attendu est :

```php
public function process($request, $response, $next) {
    ob_start();
    $result = $next();
    $content = ob_get_clean();
    // compression gzip ici
    return $result;
}
```

À l’état actuel, il passe simplement la requête sans transformation, ce qui laisse l’extension à votre implémentation métier.
