# APIKey Query Authentication

Authentification par clé API en paramètre de requête.

## Description

`APIKeyQuery` valide une clé API transmise en tant que paramètre de requête dans l'URL. Moins sécurisée que le header, mais utile pour certains cas.

## Configuration

```php
use Eorbahapi\Security\APIKey\APIKeyQuery;

$app = new EorbahAPI('API');

$app->register(APIKeyQuery::class, new APIKeyQuery(
    param: 'api_key'  // Nom du paramètre en URL
));

$app->get('/data', function (APIKeyQuery $apiKey) {
    $key = $apiKey->__invoke();  // Récupère la clé
    return ['key' => $key];
});
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `param` | string | Nom du paramètre de requête (ex: `api_key`) |

## Utilisation

### URL avec paramètre

```
https://api.example.com/data?api_key=your-api-key-here
```

### Client (curl)

```bash
curl "https://api.example.com/data?api_key=your-api-key-here"
```

### Client (JavaScript/fetch)

```javascript
fetch('https://api.example.com/data?api_key=your-api-key-here')
  .then(r => r.json())
  .then(data => console.log(data))
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Security\APIKey\APIKeyQuery;

$app = new EorbahAPI('API');

$app->register(APIKeyQuery::class, new APIKeyQuery(param: 'api_key'));

$app->get('/search', function (APIKeyQuery $apiKey, $q) {
    $key = $apiKey->__invoke();
    
    // Valider la clé
    if (!isValidKey($key)) {
        return ['error' => 'Invalid API key', 'status' => 401];
    }
    
    // Effectuer la recherche
    return ['query' => $q, 'results' => []];
});

$app->run();
```

## ⚠️ Avertissements

- **Moins sécurisé** : la clé apparaît dans l'URL (logs, historique navigateur)
- L'URL peut être cachée dans les referers HTTP
- Les clés en paramètres peuvent être loggées côté serveur/proxy
- **À éviter** en production avec des données sensibles

## Quand l'utiliser ?

- APIs publiques sans données sensibles
- Scripts de test/prototypage
- Webhooks où l'authentification par header n'est pas possible
- APIs de lecture publique avec limites de débit

## Sécurité

- Préférer `APIKeyHeader` pour les APIs sensibles
- Combiner avec HTTPS obligatoire
- Limiter le débit par clé
- Exprimer les clés régulièrement
