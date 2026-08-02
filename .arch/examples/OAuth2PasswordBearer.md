Pour une utilisation en production, il faut remplacer la validation en dur et la génération de token factice par une véritable vérification des identifiants (base de données, LDAP, etc.) et une signature JWT sécurisée.

Je vous propose une refonte de la classe OAuth2PasswordBearer en respectant les principes d’injection de dépendances d’EorbahAPI (via le conteneur ou l’attribut #[Depends]).

---

🔧 Solution : classe OAuth2PasswordBearer prête pour la production

```php
<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

use EorBah545\Eorbahapi\DependencyInterface;
use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OAuth2PasswordBearer extends OAuth2 implements DependencyInterface
{
    private ?Request $request = null;
    private ?Response $response = null;

    private UserProviderInterface $userProvider;
    private string $jwtSecret;
    private string $jwtAlgo;
    private int $tokenExpiration;

    public function __construct(
        UserProviderInterface $userProvider,
        string $jwtSecret,
        string $jwtAlgo = 'HS256',
        int $tokenExpiration = 3600,
        string $tokenUrl = "token",
        array $scopes = [],
        bool $autoError = true,
        string $schemeName = "OAuth2",
        ?string $description = null
    ) {
        parent::__construct($tokenUrl, $scopes, $autoError, $schemeName, $description);
        $this->userProvider = $userProvider;
        $this->jwtSecret = $jwtSecret;
        $this->jwtAlgo = $jwtAlgo;
        $this->tokenExpiration = $tokenExpiration;
    }

    /**
     * Appelé automatiquement par DependencyResolver si l'attribut #[Depends] est utilisé.
     * Permet d'injecter Request/Response si nécessaire (par exemple pour lire les identifiants depuis le body).
     */
    public function resolve(Request $request, Response $response): mixed
    {
        $this->request = $request;
        $this->response = $response;
        return $this;
    }

    /**
     * Valide les identifiants username/password et retourne un access token JWT.
     * Utilise le UserProvider pour vérifier les identifiants.
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    public function validatePasswordGrant(string $username, string $password): array
    {
        // Vérification des identifiants via le provider
        $user = $this->userProvider->findUserByCredentials($username, $password);
        if (!$user) {
            // En cas d'erreur, on peut lever une exception ou retourner une structure d'erreur OAuth2
            throw new \Exception('Invalid credentials', 401);
        }

        // Génération du JWT
        $payload = [
            'sub' => $user->getIdentifier(),  // ex: id, email
            'username' => $user->getUsername(),
            'scopes' => $this->scopes,
            'iat' => time(),
            'exp' => time() + $this->tokenExpiration
        ];

        $accessToken = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpiration
        ];
    }

    /**
     * Optionnel : méthode pour décoder et vérifier un token (pour les endpoints protégés)
     */
    public function verifyToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgo));
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

---

🧩 Interfaces complémentaires à créer

UserProviderInterface

```php
<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

interface UserProviderInterface
{
    /**
     * Retourne un objet UserInterface si les identifiants sont valides, sinon null.
     */
    public function findUserByCredentials(string $username, string $password): ?UserInterface;
}
```

UserInterface

```php
<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

interface UserInterface
{
    public function getIdentifier(): string|int;
    public function getUsername(): string;
    // Ajoutez d'autres méthodes selon vos besoins (getRoles, getPassword, etc.)
}
```

---

🔌 Intégration dans EorbahAPI

1️⃣ Créer un UserProvider concret (ex: Doctrine, PDO)

```php
use EorBah545\Eorbahapi\security\OAuth2\UserProviderInterface;
use EorBah545\Eorbahapi\security\OAuth2\UserInterface;

class DatabaseUserProvider implements UserProviderInterface
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function findUserByCredentials(string $username, string $password): ?UserInterface
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->execute(['username' => $username]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$userData || !password_verify($password, $userData['password_hash'])) {
            return null;
        }

        return new GenericUser($userData['id'], $userData['username']);
    }
}

class GenericUser implements UserInterface
{
    private string|int $id;
    private string $username;

    public function __construct($id, string $username)
    {
        $this->id = $id;
        $this->username = $username;
    }

    public function getIdentifier(): string|int { return $this->id; }
    public function getUsername(): string { return $this->username; }
}
```

2️⃣ Enregistrer les dépendances dans le conteneur d’EorbahAPI

```php
$app = new EorBah545\Eorbahapi\EorbahAPI();

// Enregistrement PDO (exemple)
$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
$app->register(PDO::class, $pdo);

// Enregistrement du UserProvider
$app->register(UserProviderInterface::class, new DatabaseUserProvider($pdo));

// Enregistrement de l'instance OAuth2PasswordBearer avec la clé secrète
$jwtSecret = 'your-very-secret-key-change-me';
$oauth = new OAuth2PasswordBearer(
    userProvider: $app->resolver->resolve(UserProviderInterface::class)[0], // ou directement new DatabaseUserProvider($pdo)
    jwtSecret: $jwtSecret,
    jwtAlgo: 'HS256',
    tokenExpiration: 3600,
    tokenUrl: '/login',
    scopes: ['read', 'write']
);
$app->register(OAuth2PasswordBearer::class, $oauth);
```

3️⃣ Définir la route POST /login

```php
$app->post('/login', function(Response $res, OAuth2PasswordBearer $oauth) {
    // Récupération des identifiants depuis le body JSON
    $body = $this->request->body();
    $username = $body['username'] ?? '';
    $password = $body['password'] ?? '';

    try {
        $tokenData = $oauth->validatePasswordGrant($username, $password);
        $res->json($tokenData);
    } catch (\Exception $e) {
        $res->status(401)->json(['error' => 'invalid_credentials']);
    }
});
```

4️⃣ Tester avec curl

```bash
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"username":"john","password":"secret"}'
```

Réponse (exemple) :

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

---

🔐 Sécurité supplémentaire

· Utilisez HTTPS en production.
· Stockez les mots de passe avec password_hash() / password_verify().
· Changez régulièrement la clé secrète JWT.
· Ajoutez un refresh token si nécessaire.
· Validez les scopes lors de l’émission du token.

---

📦 Dépendance externe recommandée

Pour la manipulation de JWT, installez via Composer :

```bash
composer require firebase/php-jwt
```

---

Avec cette implémentation, vous obtenez une classe OAuth2PasswordBearer prête pour la production, flexible (via le UserProviderInterface) et sécurisée (JWT signé).