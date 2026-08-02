Voici un exemple concret d’utilisation de OAuth2PasswordRequestForm avec EorbahAPI pour un endpoint /token (conforme à la spécification OAuth2 Password Grant).

Cette classe est idéale pour récupérer et valider les paramètres du formulaire envoyés en application/x-www-form-urlencoded.

---

🔧 Exemple d’intégration

```php
use EorBah545\Eorbahapi\EorbahAPI;
use EorBah545\Eorbahapi\security\OAuth2\OAuth2PasswordRequestForm;
use EorBah545\Eorbahapi\security\OAuth2\OAuth2PasswordBearer;

$app = new EorbahAPI();

// Enregistrement d'un service utilisateur (exemple simple)
$userProvider = new class implements \EorBah545\Eorbahapi\security\OAuth2\UserProviderInterface {
    public function findUserByCredentials(string $username, string $password): ?\EorBah545\Eorbahapi\security\OAuth2\UserInterface {
        // Simulation : accepter uniquement admin/admin
        if ($username === 'admin' && $password === 'admin') {
            return new class implements \EorBah545\Eorbahapi\security\OAuth2\UserInterface {
                public function getIdentifier(): string|int { return 1; }
                public function getUsername(): string { return 'admin'; }
            };
        }
        return null;
    }
};

// Enregistrement du fournisseur dans le conteneur
$app->register(\EorBah545\Eorbahapi\security\OAuth2\UserProviderInterface::class, $userProvider);

// Endpoint token (password grant)
$app->post('/token', function(\EorBah545\Eorbahapi\Response $res) use ($app) {
    // 1. Récupérer le formulaire (standard OAuth2)
    $form = OAuth2PasswordRequestForm::fromRequest();
    
    // 2. Valider le formulaire (lève une exception si invalide)
    try {
        $form->validate();
    } catch (\InvalidArgumentException $e) {
        $res->status(400)->json(['error' => 'invalid_request', 'error_description' => $e->getMessage()]);
        return;
    }
    
    // 3. Vérifier les identifiants via UserProvider
    $userProvider = $app->resolver->resolve(\EorBah545\Eorbahapi\security\OAuth2\UserProviderInterface::class)[0];
    $user = $userProvider->findUserByCredentials($form->getUsername(), $form->getPassword());
    
    if (!$user) {
        $res->status(401)->json(['error' => 'invalid_grant', 'error_description' => 'Invalid username or password']);
        return;
    }
    
    // 4. Générer un token (via OAuth2PasswordBearer par exemple)
    $oauth = new OAuth2PasswordBearer($userProvider, 'votre_secret_jwt');
    $tokenData = $oauth->validatePasswordGrant($form->getUsername(), $form->getPassword());
    
    // 5. Retourner la réponse OAuth2 standard
    $res->json($tokenData);
});

$app->run();
```

---

📌 Exemple de requête curl

```bash
curl -X POST http://localhost:8000/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=password&username=admin&password=admin&scope=read write"
```

Réponse (succès) :

```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "bearer",
  "expires_in": 3600
}
```

---

🧠 Utilisation avancée avec injection automatique

Si vous souhaitez que le DependencyResolver injecte automatiquement le formulaire, vous devez modifier la classe pour qu’elle ait un constructeur sans paramètres obligatoires (ex: valeur par défaut pour tous). Actuellement, tous les paramètres sont optionnels, donc c’est déjà le cas. Vous pouvez donc écrire :

```php
$app->post('/token', function(Response $res, OAuth2PasswordRequestForm $form) {
    $form->validate();
    // ...
});
```

Il faudrait toutefois que le formulaire soit capable de remplir ses propriétés sans appeler fromRequest() explicitement. Pour cela, modifiez la classe pour que, si les propriétés sont null au moment de l’appel, elle aille lire automatiquement $_POST. Une solution élégante est d’appeler fromPost() dans le constructeur si les données ne sont pas fournies. Actuellement, le constructeur ne le fait que si $username === null et isset($_POST['username']). Il suffirait de remplacer par :

```php
public function __construct(...) {
    // ... initialisation
    if ($this->username === null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $this->fromPost();
    }
}
```

Ainsi, une instance créée par le résolveur (sans arguments) chargera automatiquement les données POST.

---

✅ Récapitulatif

· OAuth2PasswordRequestForm simplifie la réception et validation des paramètres du Password Grant OAuth2.
· Il lit $_POST par défaut (standard application/x-www-form-urlencoded).
· Parfait pour un endpoint /token qui délivre des jetons d’accès.
· Peut être injecté automatiquement par le DependencyResolver si vous ajustez légèrement le constructeur pour charger les données POST lorsque l’instance est créée sans arguments.