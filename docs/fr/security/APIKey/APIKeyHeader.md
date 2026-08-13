# APIKey Header Authentication

Authentification par clé API dans l'en-tête HTTP.

## Description

`APIKeyHeader` valide une clé API transmise via un en-tête HTTP personnalisé. C'est la méthode la plus courante pour les APIs.

## Configuration

```php
use Eorbahapi\Security\APIKey\APIKeyHeader;

$app = new EorbahAPI('API');

// Enregistrer la sécurité
$app->register(APIKeyHeader::class, new APIKeyHeader(
    param: 'X-API-Key'  // Nom de l'en-tête
));

$app->get('/protected', function (APIKeyHeader $apiKey) {
    $key = $apiKey->__invoke();  // Récupère la clé
    return ['key' => $key];
});
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `param` | string | Nom de l'en-tête HTTP (ex: `X-API-Key`) |

## Utilisation

### Client (curl)

```bash
curl -H "X-API-Key: your-api-key-here" https://api.example.com/protected
```

### Client (JavaScript/fetch)

```javascript
fetch('https://api.example.com/protected', {
  headers: {
    'X-API-Key': 'your-api-key-here'
  }
})
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Security\APIKey\APIKeyHeader;
use Eorbahapi\Response;

$app = new EorbahAPI('API');

$app->register(APIKeyHeader::class, new APIKeyHeader(param: 'X-API-Key'));

$app->get('/data', function (APIKeyHeader $apiKey, Response $res) {
    $key = $apiKey->__invoke();
    
    // Valider la clé
    $validKeys = ['sk_live_12345', 'sk_test_67890'];
    if (!in_array($key, $validKeys)) {
        $res->status(401);
        return ['error' => 'Invalid API key'];
    }
    
    return ['data' => 'sensitive information'];
});

$app->run();
```

## Cas d'usage

- APIs publiques avec limites de débit par clé
- Intégrations serveur-à-serveur
- Applications mobiles (la clé est dans les headers)
- Webhooks sécurisés

## Notes

- L'en-tête doit être configuré dans les CORS si appelé depuis un navigateur
- Ne jamais exposer les clés API dans le code source
- Utiliser HTTPS en production
- Stocker les clés dans des variables d'environnement ou un gestionnaire de secrets
