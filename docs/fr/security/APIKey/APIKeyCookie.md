# APIKey Cookie Authentication

Authentification par clé API dans un cookie HTTP.

## Description

`APIKeyCookie` valide une clé API stockée dans un cookie HTTP. Utile pour les applications web où l'authentification persiste avec les sessions.

## Configuration

```php
use Eorbahapi\Security\APIKey\APIKeyCookie;

$app = new EorbahAPI('API');

$app->register(APIKeyCookie::class, new APIKeyCookie(
    param: 'api_session'  // Nom du cookie
));

$app->get('/protected', function (APIKeyCookie $apiKey) {
    $key = $apiKey->__invoke();  // Récupère la clé du cookie
    return ['authenticated' => true];
});
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `param` | string | Nom du cookie (ex: `api_session`) |

## Utilisation

### Définir le cookie

```php
use Eorbahapi\Response;

$app->post('/login', function (Response $res) {
    // Après authentification réussie
    $res->cookie(
        name: 'api_session',
        value: 'sk_live_12345',
        expires: time() + 86400,  // 24 heures
        httponly: true,           // Important: non accessible via JS
        secure: true              // HTTPS only
    );
    
    return ['message' => 'Logged in'];
});
```

### Accéder au cookie

```php
$app->get('/data', function (APIKeyCookie $apiKey) {
    $sessionKey = $apiKey->__invoke();
    
    if (!$sessionKey) {
        return ['error' => 'Not authenticated'];
    }
    
    return ['data' => 'your data'];
});
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Security\APIKey\APIKeyCookie;
use Eorbahapi\Response;

$app = new EorbahAPI('API');

$app->register(APIKeyCookie::class, new APIKeyCookie(param: 'api_session'));

$app->post('/login', function (Request $req, Response $res) {
    $data = $req->json();
    
    // Valider les credentials
    if (validateCredentials($data['username'], $data['password'])) {
        $sessionToken = generateToken();
        
        $res->cookie(
            name: 'api_session',
            value: $sessionToken,
            expires: time() + (7 * 86400),  // 7 jours
            httponly: true,
            secure: true,
            path: '/',
            samesite: 'Strict'
        );
        
        return ['message' => 'Login successful'];
    }
    
    $res->status(401);
    return ['error' => 'Invalid credentials'];
});

$app->get('/profile', function (APIKeyCookie $apiKey) {
    $token = $apiKey->__invoke();
    
    if (!$token || !isValidToken($token)) {
        return ['error' => 'Unauthorized', 'status' => 401];
    }
    
    return ['user' => getCurrentUser($token)];
});

$app->post('/logout', function (Response $res) {
    $res->clearCookie('api_session');
    return ['message' => 'Logged out'];
});

$app->run();
```

## Avantages

- ✅ Persiste automatiquement entre requêtes
- ✅ Géré par le navigateur et les clients HTTP
- ✅ Peut être HttpOnly (non accessible au JavaScript)
- ✅ Support natif des sessions

## Avertissements

- ⚠️ Vulnérable aux attaques CSRF (utiliser des tokens CSRF)
- ⚠️ Logué si non-secure (toujours utiliser HTTPS)
- ⚠️ Visible dans les dev tools du navigateur
- ⚠️ Partagé entre tous les domaines sans `SameSite`

## Bonnes pratiques

```php
// ✅ Configuration sécurisée
$res->cookie(
    name: 'api_session',
    value: $token,
    expires: time() + 3600,  // 1 heure
    httponly: true,          // Pas accessible au JS
    secure: true,            // HTTPS only
    path: '/',
    samesite: 'Strict'       // Protection CSRF
);
```

## Quand l'utiliser ?

- Applications web (SPA + Backend)
- Sessions d'utilisateur persistantes
- APIs serveur-à-serveur via clients HTTP
- Authentification avec reconnexion automatique

## Sécurité

- Toujours combiner avec HTTPS
- Utiliser `HttpOnly` et `Secure` flags
- Définir `SameSite=Strict` ou `Lax`
- Expirer après une durée raisonnable
- Utiliser des tokens avec rotation
