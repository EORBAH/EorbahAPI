# OAuth2PasswordBearer

Authentification OAuth2 avec grant type "password" (Resource Owner Password Credentials).

## Description

`OAuth2PasswordBearer` implémente le flux OAuth2 "password" où l'utilisateur transmet directement son login/password pour obtenir un access token. Moins sécurisé que les autres flows, mais simple pour les tests ou les clients de confiance.

## Configuration

```php
use Eorbahapi\Security\OAuth2\OAuth2PasswordBearer;

$app = new EorbahAPI('API');

$oauth = new OAuth2PasswordBearer(
    tokenUrl: '/auth/token',
    scopes: ['read', 'write', 'admin']
);

$app->register(OAuth2PasswordBearer::class, $oauth);
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `tokenUrl` | string | Endpoint pour obtenir le token |
| `scopes` | array | Scopes disponibles |

## Flux OAuth2 Password

```
1. Client → Server: POST /auth/token
   {
     "grant_type": "password",
     "username": "user@example.com",
     "password": "secret_password",
     "scope": "read write"
   }

2. Server → Client: Valider credentials
   Si valides:
   {
     "access_token": "eyJ...",
     "token_type": "Bearer",
     "expires_in": 3600,
     "scope": "read write"
   }

3. Client → Server: GET /data
   Header: Authorization: Bearer eyJ...

4. Server: Valider token et retourner data
```

## Utilisation

### 1. Endpoint de token

```php
$app->post('/auth/token', function ($request, OAuth2PasswordBearer $oauth) {
    $data = $request->json();
    
    if ($data['grant_type'] !== 'password') {
        return [
            'error' => 'unsupported_grant_type',
            'error_description' => 'Only password grant is supported'
        ];
    }
    
    // Valider les credentials
    $user = authenticateUser($data['username'], $data['password']);
    if (!$user) {
        return [
            'error' => 'invalid_grant',
            'error_description' => 'Invalid username or password'
        ];
    }
    
    // Générer le token
    $token = $oauth->generateToken($user, $data['scope'] ?? '');
    
    return [
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => $data['scope'] ?? ''
    ];
});
```

### 2. Routes protégées

```php
$app->get('/profile', function (OAuth2PasswordBearer $oauth) {
    try {
        $user = $oauth->validateToken();  // Depuis Authorization header
        return ['user' => $user];
    } catch (Exception $e) {
        return ['error' => 'Unauthorized'];
    }
});
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Security\OAuth2\OAuth2PasswordBearer;

$app = new EorbahAPI('API');

$oauth = new OAuth2PasswordBearer(
    tokenUrl: '/oauth/token',
    scopes: ['read', 'write', 'admin', 'profile']
);
$app->register(OAuth2PasswordBearer::class, $oauth);

// Endpoint d'authentification
$app->post('/oauth/token', function ($request, OAuth2PasswordBearer $oauth) {
    $data = $request->json();
    
    // Valider les paramètres
    if (!isset($data['username'], $data['password'])) {
        return ['error' => 'missing_credentials'];
    }
    
    // Authentifier
    $user = findUserByEmail($data['username']);
    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        return [
            'error' => 'invalid_grant',
            'error_description' => 'Invalid credentials'
        ];
    }
    
    // Valider les scopes
    $requestedScopes = explode(' ', $data['scope'] ?? '');
    $grantedScopes = array_intersect($requestedScopes, $user['available_scopes']);
    
    // Générer token
    $accessToken = $oauth->generateToken([
        'user_id' => $user['id'],
        'email' => $user['email'],
        'scopes' => $grantedScopes
    ]);
    
    return [
        'access_token' => $accessToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600,
        'scope' => implode(' ', $grantedScopes)
    ];
});

// Route protégée
$app->get('/users/me', function (OAuth2PasswordBearer $oauth) {
    try {
        $token = $oauth->getToken();  // Depuis Authorization: Bearer
        $user = findUserById($token['user_id']);
        
        return ['user' => $user];
    } catch (Exception $e) {
        return ['error' => 'Unauthorized', 'status' => 401];
    }
});

// Endpoint de refresh (optionnel)
$app->post('/oauth/refresh', function ($request, OAuth2PasswordBearer $oauth) {
    $data = $request->json();
    
    if ($data['grant_type'] !== 'refresh_token') {
        return ['error' => 'Invalid grant type'];
    }
    
    $oldToken = $oauth->validateRefreshToken($data['refresh_token']);
    
    $newToken = $oauth->generateToken($oldToken);
    return [
        'access_token' => $newToken,
        'token_type' => 'Bearer',
        'expires_in' => 3600
    ];
});

$app->run();
```

## Client (cURL)

```bash
# 1. Obtenir le token
curl -X POST http://api.example.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "password",
    "username": "user@example.com",
    "password": "password123",
    "scope": "read write"
  }'

# Réponse:
# {
#   "access_token": "eyJ...",
#   "token_type": "Bearer",
#   "expires_in": 3600
# }

# 2. Utiliser le token
curl -H "Authorization: Bearer eyJ..." \
  http://api.example.com/users/me
```

## Client (JavaScript)

```javascript
async function login(email, password) {
  const response = await fetch('http://api.example.com/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      grant_type: 'password',
      username: email,
      password: password,
      scope: 'read write profile'
    })
  });
  
  const { access_token, expires_in } = await response.json();
  
  // Stocker le token
  localStorage.setItem('access_token', access_token);
  localStorage.setItem('token_expires', Date.now() + expires_in * 1000);
  
  return access_token;
}

async function getProfile(accessToken) {
  const response = await fetch('http://api.example.com/users/me', {
    headers: { 'Authorization': `Bearer ${accessToken}` }
  });
  
  return response.json();
}

// Utilisation
const token = await login('user@example.com', 'password123');
const profile = await getProfile(token);
```

## ⚠️ Avertissements

- ❌ **Moins sécurisé** : l'utilisateur transmet son mot de passe au client
- ❌ Ne pas utiliser sur des connexions non-HTTPS
- ❌ Le client peut voir le mot de passe
- ❌ Pas idéal pour les applications publiques

## Quand l'utiliser ?

✅ Tests et développement
✅ Applications mobiles de confiance (de votre entreprise)
✅ Clients natifs (desktop apps)
✅ Legacy systems migration
❌ Pas pour les SPAs publiques
❌ Pas pour les intégrations tierces

## Sécurité

```php
// ✅ Bonnes pratiques
- Utiliser HTTPS en production
- Stocker les refresh tokens en HttpOnly cookies
- Access tokens en mémoire/localStorage
- Expirer rapidement (15 min pour access token)
- Long refresh tokens (7 jours)
- Rate limit sur /oauth/token
- Logger les tentatives échouées
```

## Alternative recommandée

Pour les SPAs publiques, utiliser plutôt:
- **Authorization Code flow** (+ PKCE)
- **Implicit flow** (déprécié, éviter)
