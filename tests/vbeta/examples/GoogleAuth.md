Voici un exemple d’utilisation de OAuth2AuthorizationCodeBearer avec EorbahAPI et le DependencyResolver.

🔧 Prérequis

· La classe OAuth2AuthorizationCodeBearer a deux paramètres obligatoires dans son constructeur : $authorizationUrl et $tokenUrl.
    Elle ne pourra donc pas être instanciée automatiquement par l’étape 5 du résolveur (car getNumberOfRequiredParameters() > 0).
    Vous devez soit l’enregistrer dans le conteneur, soit utiliser l’attribut #[Depends].

---

1️⃣ Enregistrement dans le conteneur (recommandé)

```php
use EorBah545\Eorbahapi\security\OAuth2\OAuth2AuthorizationCodeBearer;

// Configuration OAuth2 (exemple avec Google)
$authorizationUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
$tokenUrl = 'https://oauth2.googleapis.com/token';
$refreshUrl = 'https://oauth2.googleapis.com/token';
$scopes = ['https://www.googleapis.com/auth/userinfo.email', 'https://www.googleapis.com/auth/userinfo.profile'];

// Instanciation
$oauthCode = new OAuth2AuthorizationCodeBearer(
    authorizationUrl: $authorizationUrl,
    tokenUrl: $tokenUrl,
    refreshUrl: $refreshUrl,
    scopes: $scopes,
    autoError: true,
    schemeName: 'Google OAuth2'
);

// Enregistrement dans le conteneur d'EorbahAPI
$app->register(OAuth2AuthorizationCodeBearer::class, $oauthCode);
```

Routes avec le service injecté

```php
// 1. Rediriger l'utilisateur vers le fournisseur OAuth
$app->get('/auth/login', function(Response $res, OAuth2AuthorizationCodeBearer $oauth) {
    $clientId = 'VOTRE_CLIENT_ID';
    $redirectUri = 'https://votre-site.com/auth/callback';
    $state = bin2hex(random_bytes(16)); // à stocker en session pour vérification

    $authUrl = $oauth->createAuthorizationUrl(
        clientId: $clientId,
        redirectUri: $redirectUri,
        scopes: [], // utilise ceux du constructeur
        state: $state,
        responseType: 'code'
    );

    // Stocker $state en session (via $_SESSION ou Request)
    $_SESSION['oauth2_state'] = $state;

    $res->redirect($authUrl);
});

// 2. Callback après autorisation
$app->get('/auth/callback', function(Request $req, Response $res, OAuth2AuthorizationCodeBearer $oauth) {
    $code = $req->query()['code'] ?? null;
    $state = $req->query()['state'] ?? null;

    // Vérifier l'état CSRF
    if (!$state || $state !== ($_SESSION['oauth2_state'] ?? null)) {
        $res->status(400)->json(['error' => 'Invalid state']);
        return;
    }

    if (!$code) {
        $res->status(400)->json(['error' => 'Missing authorization code']);
        return;
    }

    $clientId = 'VOTRE_CLIENT_ID';
    $clientSecret = 'VOTRE_CLIENT_SECRET';
    $redirectUri = 'https://votre-site.com/auth/callback';

    // Échange du code contre un token
    $tokenData = $oauth->exchangeCodeForToken($code, $clientId, $clientSecret, $redirectUri);

    // Stocker le token d'accès (dans session, base de données, etc.)
    $_SESSION['access_token'] = $tokenData['access_token'];
    $_SESSION['refresh_token'] = $tokenData['refresh_token'];

    $res->json(['message' => 'Authentification réussie', 'tokens' => $tokenData]);
});

// 3. Rafraîchir le token (exemple d'endpoint)
$app->post('/auth/refresh', function(Response $res, OAuth2AuthorizationCodeBearer $oauth) {
    $refreshToken = $_SESSION['refresh_token'] ?? null;
    if (!$refreshToken) {
        $res->status(401)->json(['error' => 'No refresh token']);
        return;
    }

    $newTokens = $oauth->refreshAccessToken($refreshToken);
    $_SESSION['access_token'] = $newTokens['access_token'];

    $res->json($newTokens);
});

// 4. Route protégée nécessitant un token (Bearer)
$app->get('/api/user', function(Response $res) {
    // Ici, vous vérifiez le token d'accès stocké (ou via en-tête)
    $token = $_SESSION['access_token'] ?? null;
    if (!$token) {
        $res->status(401)->json(['error' => 'Unauthorized']);
        return;
    }

    // Normalement, vous interrogeriez l'API du fournisseur avec ce token
    $res->json(['email' => 'user@example.com']);
});
```

---

2️⃣ Alternative avec #[Depends] (sans enregistrement global)

Si vous ne voulez pas enregistrer l’instance dans le conteneur, vous pouvez utiliser l’attribut #[Depends] directement sur le paramètre de la route.

```php
use EorBah545\Eorbahapi\Attributes\Depends;

$app->get('/auth/login', function(
    Response $res,
    #[Depends(class: OAuth2AuthorizationCodeBearer::class, args: [
        'https://accounts.google.com/o/oauth2/v2/auth',
        'https://oauth2.googleapis.com/token',
        'https://oauth2.googleapis.com/token',
        ['email', 'profile'],
        true,
        'Google'
    ])]
    OAuth2AuthorizationCodeBearer $oauth
) {
    // Même code qu’avant
});
```

Inconvénient : les arguments sont dupliqués pour chaque route. Préférez l’enregistrement global dans le conteneur.

---

🔐 Note sur la sécurité

· Stockez les client_secret et autres secrets dans des variables d’environnement, pas en dur.
· Utilisez HTTPS en production.
· Validez toujours l’état (state) pour prévenir les attaques CSRF.
· En production, remplacez les méthodes generateAccessToken, generateRefreshToken par de véritables JWT ou tokens signés.

---

🧪 Test simplifié (sans vrai fournisseur)

Si vous voulez tester localement sans fournisseur OAuth externe, vous pouvez simuler un serveur d’autorisation minimal. Mais l’exemple ci-dessus est prêt pour une intégration réelle avec Google, GitHub, etc.