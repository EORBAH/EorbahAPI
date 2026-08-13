# JWT Authentication

JSON Web Token pour l'authentification stateless.

## Description

`JWT` implémente l'authentification par JSON Web Token (JWT), un standard d'authentification stateless, sans nécessiter de sessions côté serveur. Idéal pour les APIs RESTful modernes.

## Configuration

```php
use Eorbahapi\Security\JWTAuth\JWT;

$app = new EorbahAPI('API');

// Enregistrer JWT
$app->register(JWT::class, new JWT(
    secret: 'your-secret-key-min-32-chars',
    algorithm: 'HS256'
));

$app->get('/protected', function (JWT $jwt) {
    $payload = $jwt->decode();  // Décoder et valider le token
    return ['user_id' => $payload['sub']];
});
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `secret` | string | Clé secrète (min 32 caractères recommandé) |
| `algorithm` | string | Algorithme de signature (`HS256`, `HS512`, `RS256`, etc.) |

## Structure d'un JWT

```
header.payload.signature
```

**Header** (Base64):
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

**Payload** (Base64):
```json
{
  "sub": "user_123",
  "email": "user@example.com",
  "iat": 1693123456,
  "exp": 1693209856
}
```

**Signature** (HMAC):
```
HMACSHA256(base64(header) + '.' + base64(payload), secret)
```

## Utilisation

### 1. Générer un token (Login)

```php
use Eorbahapi\Security\JWTAuth\JWT;

$app->post('/login', function ($request, JWT $jwt) {
    $credentials = $request->json();
    
    // Valider les identifiants
    if (validateCredentials($credentials['username'], $credentials['password'])) {
        $payload = [
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 3600  // 1 heure
        ];
        
        $token = $jwt->encode($payload);
        return ['access_token' => $token, 'token_type' => 'Bearer'];
    }
    
    return ['error' => 'Invalid credentials'];
});
```

### 2. Utiliser le token (Routes protégées)

```php
$app->get('/profile', function (JWT $jwt) {
    try {
        $payload = $jwt->decode();  // Automatiquement du header Authorization
        return ['user' => $payload];
    } catch (TokenExpiredError $e) {
        return ['error' => 'Token expired'];
    } catch (JsonWebTokenError $e) {
        return ['error' => 'Invalid token'];
    }
});
```

### 3. Client (JavaScript)

```javascript
// 1. Récupérer le token
const response = await fetch('http://api.example.com/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ username: 'user', password: 'pass' })
});
const { access_token } = await response.json();
localStorage.setItem('token', access_token);

// 2. Utiliser le token
const profile = await fetch('http://api.example.com/profile', {
  headers: { 'Authorization': `Bearer ${access_token}` }
});
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\TokenExpiredError;
use Eorbahapi\Security\JWTAuth\JsonWebTokenError;

$app = new EorbahAPI('API');

$jwt = new JWT(
    secret: getenv('JWT_SECRET'),
    algorithm: 'HS256'
);
$app->register(JWT::class, $jwt);

// Login
$app->post('/login', function ($request, JWT $jwt) {
    $data = $request->json();
    
    $user = findUser($data['email']);
    if (!$user || !password_verify($data['password'], $user['password_hash'])) {
        return ['error' => 'Invalid credentials'];
    }
    
    $payload = [
        'sub' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'iat' => time(),
        'exp' => time() + 86400  // 24 heures
    ];
    
    $token = $jwt->encode($payload);
    return ['access_token' => $token, 'expires_in' => 86400];
});

// Route protégée
$app->get('/profile', function (JWT $jwt) {
    try {
        $payload = $jwt->decode();
        $user = findUserById($payload['sub']);
        return ['user' => $user];
    } catch (TokenExpiredError $e) {
        return ['error' => 'Token expired', 'status' => 401];
    } catch (JsonWebTokenError $e) {
        return ['error' => 'Invalid token', 'status' => 401];
    }
});

// Refresh token
$app->post('/refresh', function (JWT $jwt) {
    try {
        $oldPayload = $jwt->decode();
        
        $newPayload = array_merge($oldPayload, [
            'iat' => time(),
            'exp' => time() + 86400
        ]);
        
        $newToken = $jwt->encode($newPayload);
        return ['access_token' => $newToken];
    } catch (Exception $e) {
        return ['error' => 'Cannot refresh token'];
    }
});

$app->run();
```

## Avantages

✅ Stateless (pas de session côté serveur)
✅ Scalable (fonctionne bien en microservices)
✅ Mobile-friendly
✅ CORS-compatible
✅ Standard open (RFC 7519)
✅ Self-contained (inclut les données utilisateur)

## Avertissements

⚠️ Le token n'est pas chiffré (juste encodé Base64)
⚠️ Difficile à révoquer avant expiration
⚠️ Plus volumineux qu'une session ID
⚠️ XSS vulnérable si stocké en localStorage

## Bonnes pratiques

```php
// ✅ Payload sécurisé
$payload = [
    'sub' => $user['id'],        // Subject (user ID)
    'email' => $user['email'],   // Identifiant
    'role' => $user['role'],     // Rôle (pour l'autorisation)
    'iat' => time(),             // Issued At
    'exp' => time() + 3600       // Expiration (1 heure)
];

// ❌ À éviter
// Ne pas inclure passwords, tokens sensibles, etc.
```

## Stockage du token

**Côté client:**
- localStorage : simple mais vulnérable XSS
- sessionStorage : limité à l'onglet
- Cookie HttpOnly : sécurisé mais CSRF à gérer
- Mémoire : perdu au refresh

**Recommandé:**
```javascript
// Mémoire + refresh token en cookie HttpOnly
let accessToken = null;  // En mémoire, perdu au refresh

// Server définit le refresh token en cookie HttpOnly
document.cookie = "refreshToken=...; HttpOnly; Secure; SameSite=Strict";
```

## Revocation

Pour révoquer avant expiration:
```php
// Option 1: Blacklist en Redis
$redis->setex("blacklist:$tokenJti", 3600, true);

// Option 2: Vérifier en base
$isBlacklisted = $db->query("SELECT * FROM token_blacklist WHERE jti = ?", [$tokenJti]);

// Option 3: Rotation de clés (plus simple)
// Changer la clé secrète pour invalider tous les tokens
```

## Cas d'usage

- APIs RESTful publiques
- Authentification SPAs (Single Page Applications)
- Microservices inter-services
- Authentification mobile
- Intégrations tiers
