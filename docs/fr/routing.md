# Routage

## Ce que vous allez apprendre

Enregistrer des routes pour chaque verbe HTTP, utiliser des paramètres de chemin,
comprendre les priorités, et organiser les routes en contrôleurs et en routers.

## Les verbes HTTP

Chaque verbe a sa méthode, avec la même signature :

```php
<?php

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI();

$app->get('/items', fn () => ['liste' => []]);
$app->post('/items', fn () => ['créé' => true]);
$app->put('/items/{id}', fn () => ['remplacé' => true]);
$app->patch('/items/{id}', fn () => ['modifié' => true]);
$app->delete('/items/{id}', fn () => ['supprimé' => true]);
$app->head('/items', fn () => null);
$app->options('/items', fn () => null);

$app->run();
```

Pour plusieurs verbes d'un coup :

```php
<?php

$app->add_api_route('/items', ['GET', 'POST'], function () {
    return ['méthode' => 'GET ou POST'];
});
```

## Routes statiques et dynamiques

Un segment entre accolades devient un paramètre :

```php
<?php

$app->get('/users/{id}', fn () => 'utilisateur');
$app->get('/files/{chemin:path}', fn () => 'fichier imbriqué');
```

- `{id}` capture un segment (`[^/]+`) ;
- `{chemin:path}` capture le reste du chemin (`.*`), slashs compris.

Les routes statiques sont prioritaires sur les routes paramétrées :

```php
<?php

$app->get('/items/{id}', fn (int $id) => "dynamique $id");
$app->get('/items/new', fn () => 'statique');
// GET /items/new  -> statique
// GET /items/42   -> dynamique 42
```

## Middlewares de route

Un middleware de route s'exécute uniquement pour cette route :

```php
<?php

use Eorbahapi\Responses\JSONResponse;

$app->get('/admin', fn () => ['ok' => true], [
    static function ($request, $response, $next) {
        if ($request->getHeader('X-Api-Key') !== 'secret') {
            return new JSONResponse(['detail' => 'Clé invalide'], 401);
        }

        return $next();
    },
]);
```

Voir [Middlewares](middlewares.md) pour les middlewares globaux et l'ordre
d'exécution.

## Route par défaut

Quand aucune route ne correspond, retournez une réponse personnalisée :

```php
<?php

use Eorbahapi\Responses\JSONResponse;

$app->default_route(404, fn () => new JSONResponse(['detail' => 'Introuvable'], 404));
```

Sans handler, `default_route(405)` fixe simplement le statut par défaut.

## Contrôleurs

Les attributs remplacent `$app->get()` pour les classes :

```php
<?php

use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Middleware;

use MyApp\Middleware\MyRouteMiddleware;

final class UsersController
{
    #[Get('/users')]
    #[Middleware(middleware: MyRouteMiddleware::class, args: [])]
    public function index(): array
    {
        return ['users' => []];
    }

    #[Post('/users')]
    public function store(): array
    {
        return ['created' => true];
    }
}

$app->include_controller(new UsersController(), '/api');
// GET  /api/users
// POST /api/users
```

Les attributs disponibles : `Route`, `Get`, `Post`, `Put`, `Patch`, `Delete`,
`Head`, `Options`, `Middleware`.

## Routers inclus

Un `APIRouter` regroupe des routes réutilisables :

```php
<?php

use Eorbahapi\APIRouter;

$router = new APIRouter('/v1', tags: ['v1']);
$router->get('/ping', fn () => ['pong' => true]);
$router->post('/items', fn () => ['created' => true]);

$app->include_router($router);
// GET /v1/ping, POST /v1/items
```

`register_routes()` charge une classe exposant `__register_routes(EorbahAPI $app)` :

```php
<?php

final class RouteRegistrar
{
    public function __register_routes(EorbahAPI $app): void
    {
        $app->get('/registre', fn () => ['ok' => true]);
    }
}

$app->register_routes(RouteRegistrar::class);
```

## Sous-applications

Une application complète peut être montée sur un préfixe :

```php
<?php

$admin = new EorbahAPI();
$admin->get('/stats', fn () => ['users' => 3]);

$app->mount('/admin', $admin);
// GET /admin/stats
```

Le préfixe est retiré de l'URI avant d'atteindre la sous-application ; les
paramètres `pathInfo()` et `uri()` sont ajustés pendant l'appel.

!!! warning "Priorité des sous-applications"
    Les sous-applications sont testées avant les routes de l'application hôte :
    un montage sur `/` intercepte toutes les requêtes.

## Récapitulatif

- une route par verbe via `get()`, `post()`, `put()`, `patch()`, `delete()`… ;
- `{name}` capture un segment, `{name:path}` capture le chemin restant ;
- les routes statiques passent avant les dynamiques ;
- les middlewares de route s'ajoutent en 3ᵉ argument ;
- un contrôleur s'inclut avec `include_controller()`, un router avec
  `include_router()`, une sous-application avec `mount()`.

[Suivant : Paramètres de chemin](path-params.md){ .md-button }
