Absolument. Créer son propre framework, c'est le meilleur moyen de maîtriser les concepts fondamentaux du Web. Ce cours va vous guider pas à pas, de la compréhension des requêtes HTTP à la création d'un framework PHP inspiré de FastAPI, en passant par l'injection de dépendances et la gestion des sous-applications.

---

Partie 1 : Les Fondations - Comprendre la Requête HTTP

Avant de coder, il faut maîtriser le sujet. Une requête HTTP est un message textuel structuré qu'un client (navigateur, application) envoie à un serveur.

1.1 La Structure d'une Requête HTTP

Une requête HTTP classique se compose de trois parties :

1. La Ligne de Requête (Request Line) : Elle définit l'action à effectuer.
   · La Méthode HTTP : Le verbe d'action (GET, POST, PUT, DELETE, etc.).
   · L'URI (Uniform Resource Identifier) : Le chemin de la ressource cible (/users, /articles/123).
   · La Version HTTP : La version du protocole (HTTP/1.1, HTTP/2).
   Exemple : GET /users?page=2 HTTP/1.1
2. Les En-têtes (Headers) : Des paires clé: valeur qui fournissent des métadonnées sur la requête ou le client. Ils sont cruciaux pour le fonctionnement du Web.
   · Host: example.com : (Obligatoire en HTTP/1.1) Le nom de domaine du serveur.
   · User-Agent: Mozilla/5.0... : Identifie le logiciel client.
   · Accept: application/json : Indique au serveur le format de réponse souhaité.
   · Content-Type: application/x-www-form-urlencoded : Indique le format du corps de la requête (pour POST/PUT).
   · Authorization: Bearer <token> : Transmet des informations d'authentification.
   · Cookie: session_id=abc123 : Envoie les cookies stockés par le client.
3. Le Corps (Body) : Optionnel, il contient les données envoyées au serveur, principalement pour les méthodes POST, PUT, PATCH. Le format est indiqué par l'en-tête Content-Type (ex: application/json, application/x-www-form-urlencoded, multipart/form-data).

1.2 La Structure d'une Réponse HTTP

Le serveur répond avec un message tout aussi structuré :

1. La Ligne de Statut (Status Line) : Indique le résultat de la requête.
   · La Version HTTP : HTTP/1.1
   · Le Code de Statut : Un nombre à 3 chiffres (200 pour OK, 404 pour Non trouvé, 500 pour Erreur serveur).
   · Le Message de Statut : Une courte phrase descriptive (OK, Not Found).
   Exemple : HTTP/1.1 200 OK
2. Les En-têtes de Réponse : Des métadonnées sur la réponse.
   · Content-Type: application/json : Le format du corps de la réponse.
   · Content-Length: 123 : La taille du corps en octets.
   · Set-Cookie: session_id=xyz; Path=/ : Demande au client de stocker un cookie.
3. Le Corps (Body) : Optionnel, il contient les données renvoyées par le serveur (le contenu HTML, le JSON, etc.).

1.3 Le Cycle de Vie d'une Requête dans un Framework

Un framework comme FastAPI ou ce que nous allons construire orchestre le traitement d'une requête de manière structurée.

1. Réception : Le serveur Web (ou le moteur PHP) reçoit la requête brute.
2. Interprétation : La requête brute est parsée et transformée en un objet Request facile à manipuler.
3. Routage : Le framework examine la méthode HTTP et l'URI de l'objet Request pour trouver la fonction/contrôleur associé.
4. Middleware (pré-requête) : La requête passe à travers une série de "filtres" (middlewares) qui peuvent la modifier, l'authentifier, la journaliser, etc..
5. Injection de Dépendances & Exécution : Les dépendances de la fonction de route (ex: l'objet Request, un service de base de données) sont résolues et injectées. La fonction est alors exécutée.
6. Génération de la Réponse : La fonction retourne des données, qui sont automatiquement converties en un objet Response (par exemple, un tableau PHP devient du JSON).
7. Middleware (post-réponse) : L'objet Response repasse à travers les middlewares (dans l'ordre inverse) pour, par exemple, ajouter des en-têtes.
8. Envoi : L'objet Response final est transformé en message HTTP brut et renvoyé au client.

---

Partie 2 : L'Architecture de FastAPI - Le Modèle à Suivre

FastAPI est un modèle d'architecture moderne. Comprendre ses composants vous aidera à les reproduire en PHP.

FastAPI repose sur 4 piliers:

1. Fondation ASGI : C'est le protocole de communication entre le serveur (Uvicorn) et le framework. Il est asynchrone. En PHP, nous n'avons pas d'équivalent standard, mais nous allons créer notre propre "contrat" entre notre TestClient et notre Application.
2. Système d'Injection de Dépendances (DI) : C'est le cœur de FastAPI. Au lieu que votre fonction aille chercher ses dépendances (new Database()), elle les déclare et le framework les injecte.

   ```python
   # En FastAPI (Python)
   from fastapi import Depends
   
   def get_db():
       return Database()
   
   @app.get("/items")
   def read_items(db: Database = Depends(get_db)):
       # 'db' est automatiquement injecté par le framework
       return db.get_items()
   ```
3. Annotations de Type : Utilisées pour la validation et la documentation automatique.
4. Documentation Automatique : Générée à partir du code (OpenAPI).

---

Partie 3 : Construction du Framework PHP - Les Briques Fondamentales

Nous allons maintenant construire notre framework en PHP en suivant l'architecture de FastAPI.

3.1 L'Objet Request (La Brique Fondamentale)

C'est le premier composant à créer. Il doit encapsuler toutes les données de la requête HTTP entrante.

```php
<?php
// src/Http/Request.php

class Request
{
    private string $method;
    private string $uri;
    private array $queryParams;
    private array $headers;
    private ?string $body;
    private array $parsedBody = []; // Pour les données POST/formulaires

    public function __construct()
    {
        // Remplir l'objet avec les superglobales de PHP
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->queryParams = $_GET;
        $this->headers = $this->extractHeaders(); // Fonction pour parser getallheaders()
        $this->body = file_get_contents('php://input') ?: null;
        $this->parseBody();
    }

    private function extractHeaders(): array
    {
        $headers = [];
        foreach (getallheaders() as $name => $value) {
            $headers[strtolower($name)] = $value;
        }
        return $headers;
    }

    private function parseBody(): void
    {
        $contentType = $this->headers['content-type'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $this->parsedBody = json_decode($this->body, true) ?? [];
        } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
            parse_str($this->body, $this->parsedBody);
        }
        // 'multipart/form-data' est déjà dans $_POST, mais on peut l'ajouter
        if (empty($this->parsedBody) && $this->method === 'POST') {
            $this->parsedBody = $_POST;
        }
    }

    // --- Méthodes d'accès aux données ---

    public function getMethod(): string { return $this->method; }
    public function getUri(): string { return $this->uri; }
    public function getQueryParams(): array { return $this->queryParams; }
    public function getHeader(string $name): ?string { return $this->headers[strtolower($name)] ?? null; }
    public function getBody(): ?string { return $this->body; }
    public function getParsedBody(): array { return $this->parsedBody; }
    public function get(string $key, $default = null) { return $this->parsedBody[$key] ?? $default; }
    public function query(string $key, $default = null) { return $this->queryParams[$key] ?? $default; }
}
```

3.2 L'Objet Response

Il encapsule la réponse HTTP.

```php
<?php
// src/Http/Response.php

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $body; // Peut être une chaîne, un tableau, etc.

    public function __construct($body = '', int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setStatusCode(int $code): self { $this->statusCode = $code; return $this; }
    public function setHeader(string $name, string $value): self { $this->headers[$name] = $value; return $this; }
    public function json(array $data, int $status = 200): self
    {
        $this->body = json_encode($data);
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json';
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
```

3.3 Le Système de Routage

Un routeur associe une méthode HTTP et un chemin à une fonction de rappel (callable).

```php
<?php
// src/Routing/Router.php

class Router
{
    private array $routes = []; // ['GET' => ['/users' => $callback, ...], ...]

    public function addRoute(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function get(string $path, callable $handler): void { $this->addRoute('GET', $path, $handler); }
    public function post(string $path, callable $handler): void { $this->addRoute('POST', $path, $handler); }
    // ... put, delete, etc.

    public function dispatch(Request $request): ?Response
    {
        $method = $request->getMethod();
        $path = parse_url($request->getUri(), PHP_URL_PATH);

        if (!isset($this->routes[$method][$path])) {
            return null; // Route non trouvée
        }

        $handler = $this->routes[$method][$path];
        // Ici, nous appellerons le handler en lui passant les dépendances
        // C'est le cœur de l'injection !
        return $this->callHandler($handler, $request);
    }

    private function callHandler(callable $handler, Request $request): Response
    {
        // Version simple : on appelle le handler en lui passant la Request
        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        // Si le handler retourne autre chose (array, string), on construit une Response
        if (is_array($result)) {
            return (new Response())->json($result);
        }
        return new Response((string) $result);
    }
}
```

---

Partie 4 : L'Injection de Dépendances (DI) - Le Cœur du Framework

C'est le mécanisme qui rend votre framework puissant et flexible. L'idée est de ne plus appeler new dans vos contrôleurs, mais de laisser le framework vous fournir les objets dont vous avez besoin.

4.1 Principe de l'Autowiring

L'autowiring est une technique avancée de DI qui utilise la réflexion (Reflection API en PHP) pour inspecter le type des paramètres d'une fonction et instancier automatiquement les classes correspondantes.

Exemple de ce que nous voulons réaliser :

```php
// Notre Application
$app->get('/users', function(UserRepository $repo, Request $request) {
    // $repo est automatiquement créé et injecté
    // $request est aussi injecté
    return $repo->getAll();
});
```

4.2 Mise en Œuvre Simple de l'Autowiring

Nous allons améliorer la méthode callHandler de notre Router.

```php
// src/Routing/Router.php (extrait)

private function callHandler(callable $handler, Request $request): Response
{
    // 1. Utiliser la réflexion pour analyser le handler
    $reflection = new \ReflectionFunction($handler);

    // 2. Récupérer les paramètres attendus
    $parameters = $reflection->getParameters();
    $args = [];

    // 3. Résoudre chaque paramètre
    foreach ($parameters as $param) {
        $paramType = $param->getType();

        // Si le paramètre est typé (ex: Request, UserRepository)
        if ($paramType && !$paramType->isBuiltin()) {
            $className = $paramType->getName();

            // Si c'est l'objet Request, on l'injecte
            if ($className === Request::class) {
                $args[] = $request;
            } else {
                // C'est une autre dépendance, on tente de l'instancier
                // C'est ici que la magie opère !
                $args[] = $this->resolveDependency($className);
            }
        } else {
            // Si le paramètre n'est pas typé (ex: $id), on essaye de le récupérer
            // des paramètres de la requête ou de la route (à améliorer)
            $args[] = null; // Ou lever une exception
        }
    }

    // 4. Exécuter le handler avec les dépendances résolues
    $result = $handler(...$args);

    // ... (suite identique)
}

private function resolveDependency(string $className): object
{
    // On pourrait avoir un conteneur DI ici pour gérer les singletons, etc.
    // Pour l'instant, on instancie juste la classe.
    return new $className();
}
```

Exemple d'utilisation :

```php
// src/Repositories/UserRepository.php
class UserRepository {
    public function getAll(): array { return ['user1', 'user2']; }
}

// index.php
$app = new Application(); // Notre classe principale
$app->get('/users', function(UserRepository $repo) {
    return $repo->getAll();
});
$app->run();
```

Lorsque vous accéderez à /users, le framework instanciera automatiquement UserRepository et l'injectera dans votre fonction. C'est ainsi que fonctionne l'injection de dépendances dans FastAPI.

---

Partie 5 : L'Application Centrale et les Sous-Applications (Mount)

FastAPI permet de "monter" (mount) des sous-applications pour créer des architectures modulaires.

5.1 Le Pattern "Mount" en PHP

L'idée est d'avoir une classe Application principale qui peut contenir d'autres instances de Application (ou de Router) sous un préfixe de chemin.

```php
<?php
// src/Core/Application.php

class Application
{
    private Router $router;
    private array $middlewares = [];
    private array $mountedApps = []; // ['/admin' => $adminApp, ...]

    public function __construct()
    {
        $this->router = new Router();
    }

    // Délègue les méthodes de routage au routeur interne
    public function get(string $path, callable $handler) { $this->router->get($path, $handler); }
    // ... post, put, etc.

    // Méthode pour monter une sous-application
    public function mount(string $prefix, Application $app): void
    {
        $this->mountedApps[$prefix] = $app;
    }

    public function addMiddleware(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    // Le point d'entrée pour traiter une requête
    public function handle(Request $request): Response
    {
        // 1. Vérifier si la requête correspond à une sous-application montée
        $path = parse_url($request->getUri(), PHP_URL_PATH);
        foreach ($this->mountedApps as $prefix => $subApp) {
            if (strpos($path, $prefix) === 0) {
                // Créer une nouvelle requête avec le chemin modifié (strip prefix)
                $subRequest = $this->stripPrefixFromRequest($request, $prefix);
                // Déléguer le traitement à la sous-application
                return $subApp->handle($subRequest);
            }
        }

        // 2. Sinon, traiter la requête avec le routeur principal
        $response = $this->router->dispatch($request);

        // 3. Appliquer les middlewares (ici, simplifié pour l'exemple)
        foreach ($this->middlewares as $middleware) {
            $response = $middleware($request, $response);
        }

        return $response ?? new Response('Not Found', 404);
    }

    private function stripPrefixFromRequest(Request $request, string $prefix): Request
    {
        // Ici, on devrait cloner la requête et modifier l'URI.
        // C'est une version simplifiée.
        $newUri = substr($request->getUri(), strlen($prefix));
        // ... créer une nouvelle Request avec le nouvel URI.
        return $request; // À implémenter correctement
    }

    public function run(): void
    {
        $request = new Request();
        $response = $this->handle($request);
        $response->send();
    }
}
```

Exemple d'utilisation :

```php
// admin_app.php
$adminApp = new Application();
$adminApp->get('/dashboard', fn() => 'Admin Dashboard');

// main_app.php
$app = new Application();
$app->get('/', fn() => 'Hello World');
$app->mount('/admin', $adminApp); // Toutes les routes sous /admin vont à $adminApp

$app->run();
```

---

Partie 6 : Les Middlewares

Les middlewares sont des fonctions qui interceptent la requête et la réponse pour effectuer des tâches transversales (logging, authentification, CORS). C'est un "pipe".

```php
// src/Core/MiddlewareStack.php (Idée de conception)

class MiddlewareStack
{
    private array $middlewares = [];

    public function add(callable $middleware): void { $this->middlewares[] = $middleware; }

    public function process(Request $request, callable $finalHandler): Response
    {
        // Créer un pipeline
        $next = $finalHandler;
        // Parcourir les middlewares en sens inverse pour les exécuter dans l'ordre
        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = function(Request $req) use ($middleware, $next) {
                return $middleware($req, $next);
            };
        }
        return $next($request);
    }
}
```

---

Partie 7 : Gérer Deux Modes d'Exécution (php -S et TestClient)

C'est une distinction cruciale dans votre projet.

7.1 Mode "Normal" : Le Serveur PHP Intégré (php -S)

C'est le fonctionnement standard. Un serveur Web (intégré à PHP) écoute sur un port, reçoit des requêtes HTTP, et exécute votre script index.php. Ce script crée une requête à partir des superglobales ($_SERVER, $_GET, etc.), la traite, et envoie la réponse.

Notre script index.php pour ce mode :

```php
// public/index.php
require_once __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php'; // Qui retourne notre Application

$app->run(); // Cette méthode crée la Request, la handle, et send la Response
```

7.2 Mode "Alive" ou "Test" : L'Injection de Requêtes (TestClient)

C'est ce qui vous permet de tester votre application sans avoir à lancer un serveur Web. Vous créez un objet Request "factice", vous l'injectez directement dans la méthode handle() de votre Application, et vous récupérez l'objet Response sans passer par le réseau.

```php
<?php
// src/Testing/TestClient.php

class TestClient
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    // Simule une requête GET
    public function get(string $uri, array $headers = []): Response
    {
        return $this->request('GET', $uri, $headers);
    }

    // Simule une requête POST
    public function post(string $uri, array $data = [], array $headers = []): Response
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $uri, $headers, json_encode($data));
    }

    private function request(string $method, string $uri, array $headers = [], ?string $body = null): Response
    {
        // Construire un objet Request "factice"
        // C'est ici que vous injectez la requête
        $request = new Request(); // Mais il faut adapter Request pour qu'il puisse être construit manuellement
        // ... setter les propriétés manuellement ($request->setMethod($method), etc.)

        // Injecter la requête dans l'application et récupérer la réponse
        return $this->app->handle($request);
    }
}
```

Pour que cela fonctionne, votre classe Request doit être flexible. Elle ne doit pas dépendre uniquement des superglobales. Utilisez un constructeur qui accepte des paramètres optionnels.

```php
// src/Http/Request.php (Version améliorée)

class Request
{
    // ... propriétés

    public function __construct(array $server = null, array $get = null, array $post = null, string $body = null)
    {
        $server = $server ?? $_SERVER;
        $get = $get ?? $_GET;
        $post = $post ?? $_POST;

        $this->method = $server['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $server['REQUEST_URI'] ?? '/';
        // ...
    }

    // Ajouter des setters pour le TestClient
    public function setMethod(string $method): self { $this->method = $method; return $this; }
    // ...
}
```

Avec ce TestClient, vous pouvez écrire des tests unitaires très efficacement.

```php
// tests/Feature/UserTest.php
$client = new TestClient($app);
$response = $client->get('/users');
assert($response->getStatusCode() === 200);
```

---

Conclusion : Une Architecture Modulaire et Testable

Félicitations ! Vous avez maintenant une vision claire de l'architecture d'un framework moderne inspiré de FastAPI en PHP. Les concepts clés à retenir sont :

1. L'injection de dépendances est le mécanisme central pour découpler votre code et le rendre testable.
2. La séparation Request/Response est fondamentale. Une requête entre, une réponse sort.
3. Le routing aiguille la requête vers le bon gestionnaire.
4. Les middlewares permettent d'intercepter et de modifier le flux Request/Response.
5. Le montage (mount) permet de créer des applications modulaires.
6. Un TestClient injecte des requêtes directement dans le cœur de l'application, rendant les tests rapides et sans infrastructure.

En construisant ces briques vous-même, vous ne dépendrez plus de code préfabriqué et vous pourrez adapter chaque composant à vos besoins précis. Bonne construction !