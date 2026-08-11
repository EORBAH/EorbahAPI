# EorbahAPI — Classe principale

La classe `Eorbahapi\EorbahAPI` est le cœur du framework. Elle gère l'enregistrement des routes, l'application des middlewares, le montage de sous-applications et l'exécution du cycle de vie d'une requête HTTP.

## Fonctionnalités principales

- Enregistrement des routes HTTP : `get()`, `post()`, `put()`, `delete()`
- Ajout de middlewares globaux et par route
- Montée de sous-applications via `mount()`
- Injection de dépendances et résolution automatique des paramètres
- Gestion du traitement des exceptions via `ExceptionHandlers`
- Enregistrement de services dans le conteneur avec `register()`

## Méthodes principales

### `register(string $id, mixed $value): self`

Enregistre un service dans le conteneur de dépendances. Il peut ensuite être injecté automatiquement dans une route par type-hint.

```php
$app->register(PDO::class, $pdo);
$app->get('/users', function (PDO $pdo, Response $res) {
    // $pdo est injecté automatiquement
    $res->json(['ok' => true]);
});
```

### `get($route, $callback): static`
### `post($route, $callback): static`
### `put($route, $callback): static`
### `delete($route, $callback): static`

Enregistre une route statique ou paramétrée.

```php
$app->get('/items/{item_id}', function (Request $req, Response $res, $item_id) {
    $res->json(['item_id' => $item_id]);
});
```

### `addMiddleware($middlewareClass, $options = []): static`

Ajoute un middleware global qui s'exécute sur chaque requête, avant le routage.

```php
$app->addMiddleware(Eorbahapi\Middlewares\CORSMiddleware::class, [
    'origin' => ['*']
]);
```

### `middleware($middlewareConfig): static`

Ajoute un middleware à la dernière route définie.

```php
$app->get('/secure', function (Response $res) {
    $res->json(['ok' => true]);
})->middleware([Eorbahapi\Middlewares\AuthMiddleware::class]);
```

### `mount(string $prefix, $app, $name = null): self`

Monte une sous-application sur un préfixe d'URL.

- `$prefix` : route de base, ex. `/admin`
- `$app` : instance de `EorbahAPI`, objet exposant `handle()`/`run()`, ou callable

```php
$app = new EorbahAPI();
$admin = new EorbahAPI('Admin');
$admin->get('/', function (Response $res) {
    $res->json(['admin' => true]);
});
$app->mount('/admin', $admin);
```

### `IncludeRoute(string $RouteClass, array $option = []): void`

Charge une route unique à partir d'une classe invocable ayant une propriété `config`.

```php
class UserRoute {
    public array $config = ['method' => 'GET', 'route' => '/users'];
    public function __invoke(Response $res) {
        $res->json(['users' => []]);
    }
}
$app->IncludeRoute(UserRoute::class);
```

### `IncludeRoutes(string $RouteClass, array $option = []): void`

Charge un ensemble de routes depuis une classe invocable. La classe doit implémenter `__invoke()` et appeler l'application passée en argument.

```php
class Routes {
    public function __invoke(EorbahAPI $app) {
        $app->get('/foo', function (Response $res) {
            $res->json(['foo' => true]);
        });
    }
}
$app->IncludeRoutes(Routes::class);
```

### `setExceptionHandler(string $exceptionClass, callable $handler): self`

Permet de remplacer ou d'ajouter un gestionnaire d'exception personnalisé.

```php
$app->setExceptionHandler(
    Eorbahapi\Exceptions\ValidationException::class,
    function ($e, Request $req, Response $res) {
        $res->status(422)->json(['error' => true, 'message' => $e->getMessage()]);
    }
);
```

### `run($http_code = "404", $handler = null): void`

Démarre le traitement de la requête actuelle. Elle lit `$_SERVER['REQUEST_METHOD']` et `$_SERVER['REQUEST_URI']`, exécute les middlewares, résout la route correspondante et renvoie la réponse.

- `$http_code = '404'` : comportement standard, retourne un `404` si aucune route ne correspond.
- si `$handler` est callable, il sera exécuté pour les routes non trouvées.

```php
$app->run();
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\ExceptionHandlers;

$app = new EorbahAPI('My API');
$exceptionHandlers = new ExceptionHandlers();
$exceptionHandlers->overrideExceptionHandlers($app);

$app->get('/hello', function (Response $res) {
    $res->json(['hello' => 'world']);
});

$app->run();
```
