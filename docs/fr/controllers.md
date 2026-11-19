# Contrôleurs

## Ce que vous allez apprendre

Organiser les routes dans des classes avec les attributs, attacher des
middlewares par classe ou par méthode, et modulariser l'application avec des
routers.

## Un contrôleur

Les attributs remplacent les appels `$app->get()` :

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Delete;

final class ArticlesController
{
    #[Get('/articles')]
    public function index(): array
    {
        return ['articles' => []];
    }

    #[Get('/articles/{id}')]
    public function show(int $id): array
    {
        return ['id' => $id];
    }

    #[Post('/articles')]
    public function store(): array
    {
        return ['created' => true];
    }

    #[Delete('/articles/{id}')]
    public function destroy(int $id): array
    {
        return ['deleted' => $id];
    }
}

$app = new EorbahAPI();
$app->include_controller(new ArticlesController());

$app->run();
```

`include_controller($instance, $prefix, $args)` monte toutes les méthodes
publiques portant un attribut de route. Un préfixe s'ajoute aux chemins :

```php
$app->include_controller(new ArticlesController(), '/api');
// GET /api/articles, GET /api/articles/{id}, …
```

Les paramètres des méthodes sont résolus comme ceux des callbacks (attributs
`#[Path]`, `#[Query]`, `#[Body]`…).

## Middlewares de contrôleur

`#[Middleware]` se pose sur la classe (toutes les routes) ou sur une méthode :

```php
<?php

use Eorbahapi\Middlewares\RateLimitingMiddleware;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Middleware;

#[Middleware(RateLimitingMiddleware::class, args: ['maxRequests' => 30])]
final class SecuredController
{
    #[Get('/public')]
    public function public(): array
    {
        return ['ok' => true];
    }

    #[Get('/private')]
    #[Middleware(SomeAuthMiddleware::class)]
    public function private(): array
    {
        return ['ok' => true];
    }
}
```

Les middlewares de classe s'exécutent avant ceux de méthode.

## Routers réutilisables

Un `APIRouter` isole un groupe de routes (version, module) :

```php
<?php

use Eorbahapi\APIRouter;

$api = new APIRouter('/v1');
$api->get('/ping', fn () => ['pong' => true]);
$api->post('/items', fn () => ['created' => true]);

$app->include_router($api);
// GET /v1/ping, POST /v1/items
```

`include_router($router, $routerClass, $args, $prefix)` accepte aussi une classe
exposant `__register_routes()` :

```php
<?php

final class V1Router
{
    public function __construct(public APIRouter $router)
    {
        $this->__register_routes();
    }

    public function __register_routes(): void
    {
        $this->router->get('/ping', fn () => ['pong' => true]);
    }
}

$app->include_router(null, V1Router::class, [], '/v1');
```

## Enregistrement par méthode dédiée

Pour une organisation différente, une classe peut enregistrer ses routes sur
l'application :

```php
<?php

final class AdminRoutes
{
    public function __register_routes(EorbahAPI $app): void
    {
        $app->get('/admin/stats', fn () => ['users' => 3]);
    }
}

$app->register_routes(AdminRoutes::class);
```

## Sous-applications

Une application complète peut aussi être montée :

```php
<?php

$admin = new EorbahAPI('Admin');
$admin->include_controller(new AdminController());

$app->mount('/admin', $admin);
```

Le préfixe est retiré avant de transmettre la requête ; les routes, la
documentation et les middlewares de la sous-application restent indépendants.

!!! tip "Documentation automatique"
    Les contrôleurs n'ont rien à documenter : leurs attributs de paramètre et
    leurs modèles alimentent OpenAPI comme les routes déclaratives — voir
    [Documentation OpenAPI](openapi.md).

## Récapitulatif

- `#[Get]`, `#[Post]`… déclarent les routes d'un contrôleur ;
- `include_controller()` monte le contrôleur, avec un préfixe optionnel ;
- `#[Middleware]` s'applique à la classe ou à la méthode ;
- `APIRouter` + `include_router()` modularisent les routes ;
- `register_routes()` charge une classe `__register_routes()` ;
- `mount()` monte une application complète.

[Suivant : Fichiers statiques](static-files.md){ .md-button }
