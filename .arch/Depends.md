L’attribut #[Depends] vous permet de déclarer explicitement une dépendance qu’EorbahAPI doit injecter dans un paramètre d’une fonction de route, d’un middleware ou d’une méthode de classe.
Il est particulièrement utile lorsque la dépendance n’est pas un type primitif (string, int, …) ni l’un des objets automatiques (Request, Response), mais une instance d’une classe métier (ex: OAuth2PasswordBearer).

🔧 Principe de fonctionnement

· Le DependencyResolver d’EorbahAPI examine chaque paramètre de la fonction appelée.
· Si le paramètre est décoré avec #[Depends], le résolveur :
  · Instancie la classe donnée (ou la récupère depuis le conteneur interne),
  · Passe éventuellement des arguments au constructeur,
  · Injecte l’instance obtenue.

📦 Deux façons d’utiliser #[Depends] avec OAuth2PasswordBearer

1️⃣ Enregistrement global dans le conteneur (recommandé)

Vous enregistrez une instance unique de OAuth2PasswordBearer une fois pour toute l’application, puis vous l’injectez simplement par type-hint (sans attribut).

```php
$app = new EorBah545\Eorbahapi\EorbahAPI();

// Enregistrement dans le conteneur
$app->register(OAuth2PasswordBearer::class, new OAuth2PasswordBearer(
    tokenUrl: '/auth/token',
    scopes: ['read', 'write']
));

// Route qui utilise l'instance
$app->post('/protected', function(Response $res, OAuth2PasswordBearer $oauth) {
    // $oauth est l'instance enregistrée
    $token = $oauth->validatePasswordGrant('user', 'pass');
    $res->json($token);
});
```

➡️ Avantage : vous n’avez pas besoin de l’attribut, le type-hint suffit.
➡️ Condition : la classe doit être enregistrée avec register().

---

2️⃣ Injection ponctuelle via #[Depends]

Si vous ne voulez pas enregistrer l’instance globalement, ou si vous avez besoin d’arguments différents pour un même type, vous utilisez l’attribut directement sur le paramètre.

```php
use EorBah545\Eorbahapi\Attributes\Depends;

$app->post('/login', function(
    Response $res,
    #[Depends(class: OAuth2PasswordBearer::class, args: ['/custom/token', ['admin']])]
    OAuth2PasswordBearer $oauth
) {
    $token = $oauth->validatePasswordGrant('admin', 'secret');
    $res->json($token);
});
```

Explication :

· class : la classe à instancier (ici OAuth2PasswordBearer::class).
· args : tableau des arguments passés au constructeur (dans l’ordre).
    → new OAuth2PasswordBearer('/custom/token', ['admin'], true, ...)

Le résolveur appellera new OAuth2PasswordBearer(...$args) et injectera l’instance.

---

🧩 Que se passe‑t‑il en coulisse ?

Le DependencyResolver (modifié précédemment) dans sa méthode resolveFromAttribute fait :

```php
private function resolveFromAttribute(Depends $attr, ?string $typeName): mixed
{
    $class = $attr->class ?? $typeName;
    // Si la classe implémente DependencyInterface, on appelle resolve()
    // Sinon, on regarde dans le conteneur ou on instancie directement
    if (isset($this->container[$class])) {
        return $this->container[$class];
    }
    return new $class(...$attr->args);
}
```

Ainsi, vous pouvez contrôler précisément la construction de l’objet.

---

⚠️ Cas particulier : dépendances qui ont besoin de Request / Response

Si votre dépendance (comme OAuth2PasswordBearer) nécessite d’accéder à la requête ou à la réponse courante, vous pouvez utiliser l’interface DependencyInterface (à créer) ou simplement accepter ces objets en paramètre du constructeur.
Le résolveur sait déjà injecter Request et Response automatiquement, donc vous pouvez faire :

```php
class OAuth2PasswordBearer {
    public function __construct(
        private Request $request,
        private Response $response,
        string $tokenUrl = "token",
        array $scopes = []
    ) {}
}
```

Et l’utiliser sans #[Depends] (car les types Request et Response sont automatiquement résolus), ou avec #[Depends] si vous voulez passer d’autres arguments.

---

✅ Résumé

Méthode Code minimal
Conteneur global $app->register(OAuth2PasswordBearer::class, $instance); puis function (OAuth2PasswordBearer $oauth) {…}
Injection directe avec #[Depends] function (#[Depends(class: ..., args: [...])] OAuth2PasswordBearer $oauth) {…}

L’attribut #[Depends] est surtout utile lorsque :

· Vous ne contrôlez pas l’instanciation globale (bibliothèque tierce).
· Vous avez besoin d’arguments différents pour la même classe selon la route.
· Vous voulez instancier « à la volée » sans polluer le conteneur.