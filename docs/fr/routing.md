# Routage

## Ce que vous allez apprendre

Enregistrer une route pour chaque verbe HTTP, déclarer une route paramétrée,
rattacher plusieurs verbes au même chemin avec `add_api_route()` — et savoir ce
que le routeur fait du slash final, de la priorité des routes et d'une URL
inconnue.

## Le code

`docs_src/RoutingExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

final class RoutingExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/items', fn () => ['items' => ['a', 'b']]);
        $app->post('/items', fn () => ['created' => true]);
        $app->put('/items/{id}', fn (\Eorbahapi\Request $request) => ['updated' => $request->param('id')]);
        $app->delete('/items/{id}', fn (\Eorbahapi\Request $request) => ['deleted' => $request->param('id')]);

        // Route statique prioritaire sur la route paramétrée.
        $app->get('/items/new', fn () => ['form' => true]);

        // Une même route pour plusieurs verbes.
        $app->add_api_route('/multi', 'GET|POST', fn () => ['multi' => true]);

        return $app;
    }
}
```

## Ce qui se passe

Une route est un couple **chemin + verbe**. Les sept verbes ont leur méthode :
`get`, `post`, `put`, `patch`, `delete`, `head`, `options`, toutes de la forme
`$app->verbe($chemin, $handler, $middlewares = [])`.

`add_api_route($chemin, $methods, $handler)` accepte soit une chaîne séparée par
des barres verticales (`'GET|POST'`), soit un tableau (`['GET', 'POST']`). Le
verbe est mis en majuscules à l'enregistrement : un `PATCH` sur `/multi` ne
trouve donc rien, puisque `/multi` n'existe que pour `GET` et `POST`.

Le routeur range les routes en deux familles :

- **routes statiques** — un chemin sans `{…}`, stocké dans `$routes[verbe][chemin]` ;
- **routes paramétrées** — un chemin avec `{…}`, stocké dans `$dynamicRoutes[verbe]`
  et compilé en expression régulière (`{id}` devient `[^/]+`, `{chemin:path}`
  devient `.*`).

La recherche consulte **d'abord les routes statiques**, puis les routes
paramétrées : une route comme `GET /items/new` ne sera donc jamais capturée par
un `{id}` égal à `new`, même si vous ajoutez plus tard `GET /items/{id}`.

La route paramétrée reçoit ses valeurs par `Request::param('id')`, qui renvoie
toujours une **chaîne** (`"7"`, pas `7`) tant qu'aucun typage n'est déclaré. Les
chapitres suivants montrent comment obtenir un vrai `int`.

### Les scénarios de l'exemple

```php
<?php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/items', json: ['items' => ['a', 'b']]),
        self::scenario('POST', '/items', json: ['created' => true]),
        self::scenario('PUT', '/items/7', json: ['updated' => '7']),
        self::scenario('DELETE', '/items/7', json: ['deleted' => '7']),
        self::scenario('GET', '/items/new', json: ['form' => true]),
        self::scenario('GET', '/multi', json: ['multi' => true]),
        self::scenario('POST', '/multi', json: ['multi' => true]),
        self::scenario('PATCH', '/multi', status: 404),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /items` | 200 | `{"items":["a","b"]}` |
| `POST /items` | 200 | `{"created":true}` |
| `PUT /items/7` | 200 | `{"updated":"7"}` |
| `DELETE /items/7` | 200 | `{"deleted":"7"}` |
| `GET /items/new` | 200 | `{"form":true}` |
| `GET /multi` | 200 | `{"multi":true}` |
| `POST /multi` | 200 | `{"multi":true}` |
| `PATCH /multi` | 404 | `Not Found` |

!!! note "Le slash final est ignoré, la racine devient la route vide"
    Le routeur normalise chaque chemin avec `rtrim($path, '/')` : `/items/` et
    même `/items//` atteignent la route `/items`. Conséquence directe : `/` est
    enregistré comme la route **vide**, et `GET /` y répond normalement. En
    revanche, la casse compte : `GET /ITEMS` ne correspond à rien.

!!! warning "`HEAD` et `OPTIONS` ne sont pas implicites"
    Déclarer `GET /items` ne crée pas la route `HEAD /items` : un `HEAD` renvoie
    `404`. Si vous voulez répondre à `head` ou `options`, enregistrez-les
    explicitement avec `$app->head(...)` ou `$app->options(...)`.

Une route inconnue renvoie le statut `404` et le corps texte `Not Found` — pas du
JSON. Vous pouvez changer ce comportement global avec `default_route()` :
[Gestion des erreurs](errors.md).

## Récapitulatif

- `$app->get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`, `options()` ;
- `add_api_route($chemin, 'GET|POST', $handler)` pour plusieurs verbes d'un coup ;
- une route statique est toujours examinée avant une route paramétrée ;
- `{id}` capture un segment, `{chemin:path}` capture le reste du chemin ;
- le slash final est retiré, donc `/` est la route vide et `/items/` vaut `/items` ;
- un verbe non enregistré ou un chemin inconnu donne `404` (`Not Found`, personnalisable avec `default_route()`).

[Suivant : Paramètres de chemin](path-params.md){ .md-button }
