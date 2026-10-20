# Contrôleurs

## Ce que vous allez apprendre

Regrouper des routes dans une classe : les attributs `#[Get]`, `#[Post]`,
`#[Route]`, le montage avec `include_controller()`, et l'usage direct du router
`Eorbahapi\APIRouter`.

## Le code

`docs_src/ControllersExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Routing\Attributes\Delete;
use Eorbahapi\Routing\Attributes\Get;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Contrôleur utilisant les attributs de route.
 */
final class ItemsController
{
    #[Get('/items')]
    public function index()
    {
        return ['items' => ['a', 'b']];
    }

    #[Post('/items')]
    public function store()
    {
        return ['created' => true];
    }

    #[Get('/items/{id}')]
    public function show(\Eorbahapi\Request $request)
    {
        return ['id' => $request->param('id')];
    }

    #[Delete('/items/{id}')]
    public function destroy(\Eorbahapi\Request $request)
    {
        return ['deleted' => $request->param('id')];
    }

    // Une même méthode sur plusieurs verbes.
    #[Route('/ping', methods: ['GET', 'POST'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function ping()
    {
        return ['pong' => true];
    }

    // Méthode publique sans attribut : pas une route.
    public function helper(): string
    {
        return 'pas une route';
    }
}

/**
 * Contrôleurs : `include_controller()` et attributs de route.
 */
final class ControllersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Toutes les routes du contrôleur sont montées sous /api.
        $app->include_controller(new ItemsController(), prefix: '/api');

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/api/items', json: ['items' => ['a', 'b']]),
            self::scenario('POST', '/api/items', json: ['created' => true]),
            self::scenario('GET', '/api/items/42', json: ['id' => '42']),
            self::scenario('DELETE', '/api/items/42', json: ['deleted' => '42']),
            self::scenario('GET', '/api/ping', json: ['pong' => true]),
            self::scenario('POST', '/api/ping', json: ['pong' => true]),
            self::scenario('GET', '/api/helper', status: 404),
            self::scenario('GET', '/items', status: 404),
        ];
    }
}
```

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /api/items` | 200 | `{"items":["a","b"]}` |
| `POST /api/items` | 200 | `{"created":true}` |
| `GET /api/items/42` | 200 | `{"id":"42"}` |
| `DELETE /api/items/42` | 200 | `{"deleted":"42"}` |
| `GET /api/ping` | 200 | `{"pong":true}` |
| `POST /api/ping` | 200 | `{"pong":true}` |
| `GET /api/helper` | 404 | `Not Found` |
| `GET /items` | 404 | `Not Found` |

### Une méthode, un attribut

Les attributs de `Eorbahapi\Routing\Attributes` sont `Get`, `Post`, `Put`,
`Patch`, `Delete`, `Head`, `Options` — chacun fixe son verbe — et `Route`, qui
accepte `methods: ['GET', 'POST']` pour partager un handler entre plusieurs
verbes. Tous héritent de `Eorbahapi\Routing\Route` : les classes qui utilisent
l'ancien emplacement `Eorbahapi\Routing\{Get, Post…}` sont reconnues de la même
façon.

Le handler est une méthode publique du contrôleur : les paramètres sont résolus
comme ceux d'une closure (voir [Dépendances](#/fr/dependencies)).

### Le montage

`$app->include_controller(new ItemsController(), prefix: '/api')` enregistre
chaque méthode publique portant un attribut de route, préfixée par `/api`. Sans
préfixe, `/items` serait servi directement. Une méthode publique sans attribut
n'est jamais montée : `helper()` répond 404, et le préfixe n'existe pas pour
`/items` sans `/api`.

`include_controller()` retourne `$this` et accepte à la place d'une instance le
nom de la classe, avec les arguments de son constructeur :
`$app->include_controller(ItemsController::class, '/api', [new Logger('debug')])`.
`include_routes()` est l'alias historique de `include_controller()`.

### Le router, sans attributs

`$app->router` est un `Eorbahapi\APIRouter`. Il expose `get`, `post`, `put`,
`patch`, `delete`, `head`, `options` et `add_api_route($chemin, $methodes,
$callback, $middlewares)`, tous utilisables directement pour construire des
routes sans contrôleur — utile pour les routes générées ou conditionnelles :
`$app->router->add_api_route('/health', ['GET'], fn () => ['status' => 'ok'])`.
`include_router($router, prefix: '/v2')` monte un `APIRouter` complet sous un
préfixe.

:::callout{type="success" title="Les middlewares voyagent avec la méthode"}
`#[Middleware(RequestIdMiddleware::class)]` sur une méthode s'applique à
toutes les routes qu'elle déclare ; sur la classe, il s'applique à tout le
contrôleur, et les middlewares de méthode s'exécutent après ceux de la
classe (voir [Middlewares](#/fr/middlewares)).
:::

:::callout{type="info" title="Pourquoi des contrôleurs ?"}
Les closures suffisent pour un exemple ou un prototype. Dès qu'un projet
grossit, la classe permet d'isoler des responsabilités, d'injecter un
service par le constructeur et de monter le même contrôleur sous deux
préfixes.
:::

## Récapitulatif

- `Eorbahapi\Routing\Attributes\{Get, Post, Put, Patch, Delete, Head, Options}` déclarent une route par verbe ;
- `#[Route('/login', methods: ['GET', 'POST'])]` partage un handler entre plusieurs verbes ;
- `$app->include_controller($instanceOuClasse, prefix: '/api', args: [...])` monte le contrôleur ;
- `include_routes()` est l'alias historique de `include_controller()` ;
- seules les méthodes publiques portant un attribut de route sont enregistrées ;
- `$app->router` (`Eorbahapi\APIRouter`) permet un enregistrement programmatique.

[Suivant : Fichiers statiques](#/fr/static-files)
