# EorbahAPI — Documentation

EorbahAPI est un micro-framework PHP permettant de construire rapidement des API REST, avec injection de dépendances, validation de données, middlewares et système de montage d'applications.

## Table des matières

### Guide principal

1. [Installation](#1-installation)
2. [Structure du projet](#2-structure-du-projet)
3. [Prise en main rapide](#3-prise-en-main-rapide)
4. [Validation des données avec BaseModel](#4-validation-des-données-avec-basemodel)
5. [Réponses HTTP modulaires](#5-réponses-http-modulaires)
6. [Injection de dépendances avec Depends](#6-injection-de-dépendances-avec-depends)
7. [Organisation des routes : IncludeRoutes et IncludeRoute](#7-organisation-des-routes--includeroutes-et-includeroute)
8. [Middlewares](#8-middlewares)
9. [Monter des sous-applications avec mount](#9-monter-des-sous-applications-avec-mount)

### Modules et composants

#### Classe principale
- [EorbahAPI](./EorbahAPI.md) — Classe cœur du framework
- [Request](./Request.md) — Accès aux données de requête
- [Response](./Response.md) — Construction des réponses HTTP
- [DependencyResolver](./DependencyResolver.md) — Injection de dépendances
- [ExceptionHandlers](./ExceptionHandlers.md) — Gestion des exceptions

#### Réponses HTTP
- [Response (Helpers)](./Response.md) — Helpers FastAPI-style
- [Validation](./Validator.md) — Validation avec BaseModel

#### Structures de données
- [Address](./datastructures/Address.md)
- [Headers](./datastructures/Headers.md)
- [FormData](./datastructures/FormData.md)
- [UploadFile](./datastructures/UploadFile.md)

#### Middlewares
- [BaseHTTPMiddleware](./middlewares/BaseHTTPMiddleware.md)
- [AuthMiddleware](./middlewares/AuthMiddleware.md)
- [CORSMiddleware](./middlewares/CORSMiddleware.md)
- [GZipMiddleware](./middlewares/GZipMiddleware.md)
- [HTTPSRedirectMiddleware](./middlewares/HTTPSRedirectMiddleware.md)
- [RateLimitingMiddleware](./middlewares/RateLimitingMiddleware.md)
- [SessionMiddleware](./middlewares/SessionMiddleware.md)
- [TrustedHostMiddleware](./middlewares/TrustedHostMiddleware.md)

#### Sécurité
- [Sécurité (Index)](./security/Index.md)
- [HTTPBasic](./security/HTTPBasic.md)
- [HTTPBasicCredentials](./security/HTTPBasicCredentials.md)
- [HTTPBearer](./security/HTTPBearer.md)
- [HTTPAuthorizationCredentials](./security/HTTPAuthorizationCredentials.md)
- [RateLimiter](./security/Ratelimiting.md)
- [APIKey](./security/APIKey/) — APIKeyHeader, APIKeyQuery, APIKeyCookie
- [JWT](./security/JWTAuth/JWT.md)
- [OAuth2](./security/OAuth2/) — OAuth2PasswordBearer, OAuth2AuthorizationCodeBearer, OpenIdConnect

#### RPC
- [JsonRPC](./rpc/JsonRpc.md)

#### Templating
- [Twig](./templating/TwigTemplates.md)
- [Tempx](./templating/TempxTemplates.md)

#### Autres
- [Logger](./Logger.md)
- [StaticFiles](./StaticFiles.md)
- [SinglePageApplication](./SinglepageApplication.md)

---

## 1. Installation

### Option A — Via Composer (Packagist)

```bash
mkdir -p mon_projet/
cd mon_projet/
composer init
composer require eor_bah545/eorbahapi
```

### Option B — Via GitHub

```bash
mkdir -p mon_projet/packages
cd mon_projet/packages
git clone https://github.com/EORBAH/EorbahAPI.git
cd ..
composer init
```

Ajoutez ensuite la dépendance locale dans votre `composer.json` :

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./packages/*",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "eor_bah545/eorbahapi": "dev-main"
  }
}
```

Puis régénérez l'autoload :

```bash
composer dump-autoload
```

---

## 2. Structure du projet

Un projet EorbahAPI type suit l'arborescence suivante :

```
mon_projet/
├── packages/        # Dépendances locales (si installation via GitHub)
├── vendor/          # Dépendances Composer
├── src/             # Code source de l'application
├── public/          # Root principale
├── main.php         # Point d'entrée de l'application
└── .env             # Variables d'environnement
```

Pour le debugage utiliser `APP_DEBUG` dans .env ou directement dans `main.php`:

Exemple de fichier `.env` :

```
APP_DEBUG=true
APP_ENV=development
```
Exemple dans `main.php`:

```php
$app = new EorbahAPI(dev: true);
```
---

## 3. Prise en main rapide

### 3.1 Créer une API minimale

```php
use Eorbahapi\EorbahAPI;

$app = new EorbahAPI('Demo');

$app->get('/', function () {
    return ['hello' => 'world'];
});

$app->get('/items/{item_id}', function ($item_id, $q = null) {
    return ['item_id' => $item_id, 'q' => $q];
});

$app->run();
```

### 3.2 Lancer le serveur

```bash
php -S localhost:3000 -t public/
```

### 3.3 Tester

```bash
curl "http://localhost:3000/items/5?q=somequery"
```

Réponse attendue :

```json
{"item_id":"5","q":"somequery"}
```

Cette API met en place :
- deux routes, `/` et `/items/{item_id}` ;
- un paramètre de chemin (`item_id`) ;
- un paramètre de requête optionnel `q`.

---

## 4. Validation des données avec BaseModel

`BaseModel` permet de valider automatiquement le corps JSON d'une requête avec des règles de type et de valeur.

### 4.1 Définir un modèle et une route PUT

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Validator\BaseModel;
use Eorbahapi\Validator\Field;

$app = new EorbahAPI('Demo');

class Item extends BaseModel {
    public string $name;
    public float $price = 0.0;
    public bool $is_offer = false;

    public static function fields(): array {
        return [
            'name' => Field::required()->minLength(2),
            'price' => Field::required()->min(0),
            'is_offer' => Field::optional(),
        ];
    }
}

$app->put('/items/{item_id}', function (Item $item, $item_id) {
    return ['item_name' => $item->name, 'item_id' => $item_id];
});

$app->run();
```

### 4.2 Tester la validation

```bash
curl -X PUT "http://localhost:3000/items/1" \
  -H "Content-Type: application/json" \
  -d '{"price": 29.99, "is_offer": true}'
```

Réponse typique :

```json
{"error":true,"status":422,"message":"Validation error","details":{"name":"Le champ 'name' est requis."}}
```

> `BaseModel` accepte aussi les alias via `Field::alias()` et supporte les règles `min()`, `max()`, `minLength()`, `maxLength()`, `email()`, `regex()` et `oneOf()`.

---

## 5. Réponses HTTP modulaires

Le framework supporte les helpers suivants dans le module `Eorbahapi\Responses` :

```php
use function Eorbahapi\Responses\JSONResponse;
use function Eorbahapi\Responses\RedirectResponse;

$app->get('/hello', function () {
    return JSONResponse(['hello' => 'world']);
});

$app->get('/old', function () {
    return RedirectResponse('/new', 301);
});
```

Les valeurs retournées directement sont encore acceptées, mais les helpers rendent le code plus lisible.

---

## 5. Injection de dépendances avec Depends

L'attribut `#[Depends]` permet de déclarer explicitement une dépendance qu'EorbahAPI doit injecter dans un paramètre de route, de middleware ou de méthode de classe. Il est particulièrement utile pour les dépendances qui ne sont ni des types primitifs (`string`, `int`, …) ni des objets automatiques (`Request`, `Response`) — par exemple une classe métier comme `OAuth2PasswordBearer`.

Fonctionnement du résolveur de dépendances :

1. Chaque paramètre de la fonction appelée est examiné.
2. Si un paramètre est décoré avec `#[Depends]`, le résolveur :
   - instancie la classe indiquée (ou la récupère depuis le conteneur interne) ;
   - lui transmet, le cas échéant, des arguments de construction ;
   - injecte l'instance obtenue dans le paramètre.

Il existe deux façons d'utiliser `#[Depends]` avec une classe.

### 5.1 Enregistrement global dans le conteneur (recommandé)

Une instance unique de la classe est enregistrée une fois pour toute l'application, puis injectée par simple type-hint.

```php
$app = new EorbahAPI();

// Enregistrement dans le conteneur
$app->register(OAuth2PasswordBearer::class, new OAuth2PasswordBearer(
    tokenUrl: '/auth/token',
    scopes: ['read', 'write']
));

// Route utilisant l'instance enregistrée
$app->post('/protected', function (OAuth2PasswordBearer $oauth) {
    $token = $oauth->validatePasswordGrant('user', 'pass');
    return $token;
});
```

**Avantage :** l'attribut n'est pas nécessaire, le type-hint suffit.
**Condition :** la classe doit avoir été enregistrée via `register()`.

### 5.2 Injection ponctuelle via #[Depends]

Utile lorsque l'instance ne doit pas être enregistrée globalement, ou lorsque des arguments différents sont nécessaires pour un même type.

```php
use Eorbahapi\Attributes\Depends;

$app->post('/login', function (
    Response $res,
    #[Depends(class: OAuth2PasswordBearer::class, args: ['/custom/token', ['admin']])]
    OAuth2PasswordBearer $oauth
) {
    $token = $oauth->validatePasswordGrant('admin', 'secret');
    return $token;
});
```

Paramètres de l'attribut :
- `class` : la classe à instancier (ici `OAuth2PasswordBearer::class`).
- `args` : tableau des arguments transmis au constructeur, dans l'ordre — équivalent à `new OAuth2PasswordBearer(...$args)`.

Le résolveur appelle ensuite le constructeur avec ces arguments et injecte l'instance obtenue.

---

## 6. Organisation des routes : IncludeRouter

Pour structurer une application plus large, les routes peuvent être regroupées dans des classes dédiées.

### 6.1 Regrouper des routes dans une classe

```php
use Eorbahapi\Attributes\Route;

class AuthRouteur {
    #[Route('/health', methods: ['POST'], middlewares: [
        [RateLimitingMiddleware::class, 'maxRequests' => 10, 'routeSpecific' => true]
    ])]
    public function health() {
        return [
            'status'    => 'ok',
            'version'   => '1.0.0',
            'timestamp' => '2026-07-08T12:00:00Z',
            'uptime'    => 123456.78
        ];
    }

    #[Route('/me', methods: ['POST'], middlewares: [])]
    public function me() {
        return ['status' => 'ok'];
    }
}
```

Seconde version

```php
class UserRouteur {
    public function __register_routes($router) {
        $router->post('/me', [$this, 'me']);
        $router->post('/health', [$this, 'health']);
    }

    public function health() {
        return [
            'status'    => 'ok',
            'version'   => '1.0.0',
            'timestamp' => '2026-07-08T12:00:00Z',
            'uptime'    => 123456.78
        ];
    }

    public function me() {
        return ['status' => 'ok'];
    }
}
```


### 6.2 Enregistrer les routes

```php
$app->IncludeRouter(AuthRouteur::class, args: []); // Inclut un groupe de routes
$app->IncludeRoutes(UserRouteur::class); // Inclut un groupe de routes `_register_routes`
```

Chaque route enregistrée répond alors normalement selon sa définition.

---

## 7. Middlewares

Les middlewares permettent d'exécuter du code avant (ou autour) du traitement d'une route, que ce soit globalement ou route par route.

### 7.1 Ajouter des middlewares globaux

```php
use Eorbahapi\Middlewares\CORSMiddleware;
use Eorbahapi\Middlewares\SessionMiddleware;
use Eorbahapi\Middlewares\RateLimitingMiddleware;

// Gestion de session
$app->addMiddleware(SessionMiddleware::class);

// CORS
$app->addMiddleware(
    CORSMiddleware::class,
    [
        'allow_origins' => [
            "http://localhost:3000",                 // API locale
            "http://localhost:8000"                  // Frontend Vite
        ],
        'allow_credentials' => true,
        'allow_methods'     => ["*"],
        'allow_headers'     => ["*"],
    ]
);

// Limitation de débit (rate limiting)
$app->addMiddleware(
    RateLimitingMiddleware::class,
    [
        'max_request' => 100,
        'timeWindow'  => 60
    ]
);
```

### 7.2 Ajouter un middleware à une route spécifique

```php
$app->get('/', function (Response $res) {
    return ["hello" => "world"];
}, [[VerifierAuthentification::class, 'role' => 'admin']]);
```

> **Attention :** `middleware()` doit être appelée immédiatement après la définition de la route concernée, avant toute autre déclaration de route.

Le tableau passé à `middleware()` contient :
- en premier élément, le nom de la classe du middleware ;
- en éléments suivants (optionnels), les options transmises à son constructeur.

### 7.3 Créer un middleware personnalisé

```php
class VerifierAuthentification
{
    public function __construct(private array $options = []) {}

    public function process($request, $response, $next)
    {
        if (!isset($_SESSION['user'])) {
            $response->status(401)->send('Non autorisé');
            return false; // Arrête la chaîne de traitement
        }

        return $next(); // Passe au middleware/handler suivant
    }
}
```

### 7.4 Exemple complet

```php
$app = new EorbahAPI('Mon API');

// 1. Middleware global
$app->addMiddleware(SessionMiddleware::class);

// 2. Route avec middleware spécifique
$app->get('/profil/{id}', function ($id) {
    return ['id' => $id];
}, [[VerifierAuthentification::class, 'scope' => 'profil']]);

// 3. Lancement
$app->run();
```

---

## 8. Monter des sous-applications avec mount

`mount()` permet d'intégrer une application secondaire au sein de l'application principale : logique métier personnalisée, fichiers statiques (`StaticFiles`), voire à terme `JsonRPC` ou `MCP` (implémentation prévue ultérieurement).

### 8.1 Monter des fichiers statiques

```php
// Fichiers statiques du frontend
$app->mount("/static", new StaticFiles("frontend/dist"), "frontend");
```

### 8.2 Créer sa propre classe montable

Toute classe destinée à être montée doit exposer une interface compatible avec `mount()` :

```php
use Eorbahapi\BaseMountedApplication;

class MaClass extends BaseMountedApplication {
    /**
     * Point d'entrée principal.
     * $this->request, $this->response injecter
     */
    public function process($request, $response): void {
        // Votre logique métier ici
    }
}

$app->mount("/static", new MaClass(/* ...args */), "frontend");
```

### 8.3 Monter une sous-application EorbahAPI complète

```php
$app = new EorbahAPI("Application principale");
$api = new EorbahAPI("Application secondaire");

// ... déclaration des routes ici

$app->mount("/api", $api, "internal api app");

$app->run();
```

Toutes les routes définies dans `AuthRouteur`, par exemple, deviennent alors accessibles via `/api/me`.

### 8.4 Exemple complet avec middlewares imbriqués

```php
// Application principale
$main = new EorbahAPI('API principale');
$main->addMiddleware(LoggerGlobal::class); // S'exécute pour toutes les requêtes

// Sous-application admin
$admin = new EorbahAPI('Admin');
$admin->addMiddleware(AuthGlobal::class); // S'exécute pour /admin/...

$admin->get('/dashboard', function () {
    return 'Dashboard';
}, [CheckRole::class, 'admin']); // Spécifique à /admin/dashboard

$admin->get('/stats', function () {
    return 'Statistiques';
}); // Aucun middleware spécifique

// Montage
$main->mount('/admin', $admin);

// Lancement
$main->run();
```