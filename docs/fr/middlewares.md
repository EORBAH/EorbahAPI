# Middlewares

## Ce que vous allez apprendre

Écrire un middleware avec `MiddlewareInterface`, l'enregistrer globalement ou sur
une route, interrompre la chaîne en retournant une `Response`, déclarer un
middleware par l'attribut `#[Middleware]` sur une méthode ou sur une classe, et
savoir dans quel ordre les middlewares s'exécutent.

## Le code

`docs_src/MiddlewaresExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Middleware qui ajoute un identifiant de requête à la réponse.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $response->headers['X-Request-Id'] = $request->getHeader('X-Request-Id') ?? 'genere';

        return $next();
    }
}

/**
 * Middleware qui refuse les requêtes sans en-tête d'autorisation.
 */
final class GuardMiddleware implements MiddlewareInterface
{
    public function __construct(private string $header = 'X-Token') {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($request->getHeader($this->header) === null) {
            return new \Eorbahapi\Responses\JSONResponse(['error' => 'jeton manquant'], 403);
        }

        return $next();
    }
}

/**
 * Contrôleur illustrant les attributs de route et de middleware.
 */
final class MiddlewaresController
{
    #[Route('/login', methods: ['GET'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function login()
    {
        return ['status' => 'ok'];
    }

    #[Post('/private')]
    #[Middleware(GuardMiddleware::class, args: ['header' => 'X-Token'])]
    public function privateArea()
    {
        return ['private' => true];
    }
}
```

Et l'application qui les branche :

```php
final class MiddlewaresExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Middleware global : s'applique à toutes les routes.
        $app->add_middleware(RequestIdMiddleware::class);

        $app->get('/public', fn () => ['public' => true]);

        // Middleware de route via le paramètre de route.
        $app->get('/guarded', fn () => ['guarded' => true], [GuardMiddleware::class]);

        // Contrôleur avec #[Middleware].
        $app->include_controller(new MiddlewaresController());

        return $app;
    }
}
```

Un middleware reçoit la requête, la réponse **partagée** et un successeur
`$next`. Il peut lire et écrire sur la réponse avant l'appel, puis après. S'il
n'appelle jamais `$next()`, la chaîne s'arrête : c'est le *short-circuit* utilisé
par `GuardMiddleware` pour répondre `403` sans exécuter le handler.

## Ce qui se passe

| Requête | Statut | Corps / en-têtes |
| --- | --- | --- |
| `GET /public` | 200 | `{"public":true}` + `X-Request-Id: genere` |
| `GET /guarded` sans `X-Token` | 403 | `{"error":"jeton manquant"}` |
| `GET /guarded` avec `X-Token: abc` | 200 | `{"guarded":true}` |
| `GET /login` | 200 | `{"status":"ok"}`, middleware posé par attribut |
| `POST /private` avec `X-Token: abc` | 200 | `{"private":true}` |
| `POST /private` sans `X-Token` | 403 | `{"error":"jeton manquant"}` |

Les écritures possibles, du plus large au plus précis :

| Portée | Écriture |
| --- | --- |
| Global | `$app->add_middleware(RequestIdMiddleware::class)` |
| Global avec arguments | `$app->add_middleware(GuardMiddleware::class, ['header' => 'X-Token'])` |
| Route | 3ᵉ argument de `$app->get(...)` : `[GuardMiddleware::class]` |
| Méthode | `#[Middleware(GuardMiddleware::class, args: ['header' => 'X-Token'])]` |
| Classe | `#[Middleware(...)]` sur le contrôleur, pour toutes ses routes |

Les arguments d'un middleware sont ceux de son constructeur : un tableau à clés
(`['header' => 'X-Token']`) utilise les arguments nommés de PHP, un tableau
indexé transmet les valeurs dans l'ordre. L'attribut `#[Middleware]` est
répétable et vit dans `Eorbahapi\Routing\Attributes\Middleware` ; sur une classe,
ses middlewares passent **avant** ceux des méthodes, et les middlewares de route
s'insèrent entre les deux.

L'ordre d'exécution est l'ordre d'enregistrement, mais chaque middleware
**enveloppe** le suivant : pour deux middlewares `A` puis `B`, on obtient
`before-a → before-b → handler → after-b → after-a`. Les en-têtes posés avant
`$next()` sont donc visibles par les middlewares suivants, et ceux posés après
`$next()` écrasent les précédents.

Le style historique reste accepté : une classe qui expose
`process(Request $request, Response $response, callable $next)` est appelée comme
un `__invoke()`. Le framework essaie `process()`, puis `handle()`, puis
l'invocation directe de l'instance.

!!! tip "Middlewares globaux : instance ou classe"
    `add_middleware()` accepte une classe (instanciée une seule fois et réutilisée
    pour toutes les requêtes) ou une instance déjà construite, pratique pour
    injecter une dépendance comme un client de cache ou un limiteur de débit.

!!! warning "Ne retournez pas `null` après un court-circuit"
    Si vous interrompez la chaîne, retournez la `Response` (ou un tableau, qui
    sera encodé). Un `null` signifie « rien à changer » et laisse la réponse
    partagée telle quelle, avec le statut `200` par défaut.

## Récapitulatif

- un middleware est un `MiddlewareInterface` avec `__invoke(Request, Response, callable $next)` ;
- `$app->add_middleware()` l'applique à toutes les routes, le 3ᵉ argument de `$app->get()` à une route ;
- `#[Middleware(Classe::class, args: [...])]` déclare la même intention sur une méthode ou une classe ;
- retourner une `Response` sans appeler `$next()` coupe la chaîne ;
- l'ordre est enveloppé : `before-a, before-b, after-b, after-a` ;
- le style `process(Request, Response, callable $next)` reste pris en charge.

[Suivant : Contrôleurs](controllers.md){ .md-button }
