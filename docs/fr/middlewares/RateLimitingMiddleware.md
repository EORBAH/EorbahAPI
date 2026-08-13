# RateLimitingMiddleware

Limitation de débit (rate limiting) pour protéger l'API contre les surcharges et les abus.

## Description

Le middleware `RateLimitingMiddleware` contrôle le nombre de requêtes autorisées dans une fenêtre de temps donnée, par défaut en se basant sur l'adresse IP du client.

## Configuration

```php
use Eorbahapi\Middlewares\RateLimitingMiddleware;

$app->addMiddleware(
    RateLimitingMiddleware::class,
    [
        'max_request' => 100,        // Nombre max de requêtes
        'timeWindow'  => 60,         // Fenêtre en secondes
        'key'         => 'ip'        // Clé de limitation (défaut: ip)
    ]
);
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `max_request` | int | Nombre maximum de requêtes dans la fenêtre |
| `timeWindow` | int | Durée de la fenêtre en secondes |
| `key` | string | Clé de limitation : `ip` ou `user_id` |

## Réponse en cas de dépassement

Lorsque le nombre max de requêtes est atteint, le middleware retourne une réponse `429 Too Many Requests` :

```json
{
  "error": true,
  "status": 429,
  "message": "Rate limit exceeded"
}
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\RateLimitingMiddleware;

$app = new EorbahAPI('API');

// 100 requêtes par minute par IP
$app->addMiddleware(
    RateLimitingMiddleware::class,
    [
        'max_request' => 100,
        'timeWindow'  => 60
    ]
);

$app->get('/api/data', function () {
    return ['status' => 'ok'];
});

$app->run();
```

## Notes

- Le stockage par défaut utilise Redis via la classe `RateLimiter`
- Assurez-vous que Redis est configuré et accessible
- La clé peut être personnalisée pour supporter d'autres schémas de limitation (par utilisateur, par domaine, etc.)
