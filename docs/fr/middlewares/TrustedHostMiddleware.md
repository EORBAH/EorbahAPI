# TrustedHostMiddleware

Protection contre les attaques Host Header Injection en validant l'en-tête `Host` reçu.

## Description

Le middleware `TrustedHostMiddleware` vérifie que l'en-tête `Host` reçu correspond à une liste de domaines autorisés. Cela prévient les attaques Host Header Injection et les cache poisoning.

## Configuration

```php
use Eorbahapi\Middlewares\TrustedHostMiddleware;

$app->addMiddleware(
    TrustedHostMiddleware::class,
    [
        'allowed_hosts' => ['example.com', 'api.example.com', 'localhost:3000']
    ]
);
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `allowed_hosts` | array | Liste des domaines autorisés (avec port si nécessaire) |

## Réponse en cas de rejet

Si l'en-tête `Host` ne figure pas dans la liste blanche, le middleware retourne une réponse `400 Bad Request` :

```json
{
  "error": true,
  "status": 400,
  "message": "Invalid host header"
}
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\TrustedHostMiddleware;

$app = new EorbahAPI('Secure API');

// Configuration des hôtes autorisés
$app->addMiddleware(
    TrustedHostMiddleware::class,
    [
        'allowed_hosts' => [
            'api.example.com',
            'api.staging.example.com',
            'localhost:3000',
            'localhost:8000'
        ]
    ]
);

$app->get('/health', function () {
    return ['status' => 'ok'];
});

$app->run();
```

## Cas d'usage

```bash
# Requête valide (Host dans la liste blanche)
curl -H "Host: api.example.com" http://localhost/health
# Réponse: {"status": "ok"}

# Requête rejetée (Host non autorisé)
curl -H "Host: evil.com" http://localhost/health
# Réponse: {"error": true, "status": 400, "message": "Invalid host header"}
```

## Notes

- Incluez aussi `localhost` pour le développement local
- Spécifiez le port si votre API écoute sur un port non-standard
- Peut être combiné avec `HTTPSRedirectMiddleware` pour renforcer la sécurité
- L'en-tête `X-Forwarded-Host` est également accepté si derrière un proxy (à configurer)
