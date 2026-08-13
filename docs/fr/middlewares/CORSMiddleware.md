# CORSMiddleware

`CORSMiddleware` applique les en-têtes CORS sur les réponses HTTP et gère les requêtes `OPTIONS`.

## Exemple

```php
use Eorbahapi\Middlewares\CORSMiddleware;

$app->addMiddleware(CORSMiddleware::class, [
    'allow_origins' => ['*'],
    'allow_credentials' => false,
    'allow_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
    'allow_headers' => ['Content-Type', 'Authorization'],
]);
```

## Comportement

- autorise les origines selon la configuration
- fixe `Access-Control-Allow-Origin`
- fixe `Access-Control-Allow-Methods`
- fixe `Access-Control-Allow-Headers`
- répond `204` sur les requêtes `OPTIONS`

Le middleware utilise aussi le helper `JSONResponse` pour renvoyer une erreur structurée si l’origine n’est pas autorisée.
