# Middlewares

## Ce que vous allez apprendre

Envelopper le pipeline avec des middlewares, comprendre leur ordre d'exécution,
utiliser ceux fournis et écrire le vôtre.

## Le contrat

Un middleware est un callable `(Request $request, Response $response, callable $next)` qui retourne une valeur normalisable en réponse. L'interface
`Eorbahapi\Middlewares\MiddlewareInterface` hérite de
`Eorbahapi\Contracts\MiddlewareInterface` :

```php
<?php

use Eorbahapi\Request;
use Eorbahapi\Response;

final class TraceMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $response->headers['X-Trace'] = 'on';
        $result = $next();
        $response->headers['X-Fini'] = 'oui';

        return $result;
    }
}

$app->add_middleware(TraceMiddleware::class);
```

`$next()` exécute le middleware suivant puis la route ; son retour est
normalisé comme un retour de handler.

## Ordre d'exécution

Les middlewares globaux s'enveloppent dans l'ordre d'ajout :

```php
<?php

$app->add_middleware(new TraceMiddleware('a'));
$app->add_middleware(new TraceMiddleware('b'));
// before-a, before-b, route, after-b, after-a
```

Les middlewares de route s'exécutent après les globaux, dans l'ordre déclaré.
`#[Middleware]` sur une méthode ou une classe de contrôleur fonctionne de la
même façon.

## Arguments

```php
<?php

$app->add_middleware(TraceMiddleware::class, ['name' => 'api']);
$app->add_middleware(CORSMiddleware::class, [
    'allow_origins' => ['https://exemple.com'],
    'allow_credentials' => true,
]);
```

Un tableau à clés nommées utilise les arguments nommés ; un tableau indexé
utilise l'ordre positionnel.

## Middlewares fournis

| Middleware | Rôle |
| --- | --- |
| `CORSMiddleware` | en-têtes CORS et réponses `OPTIONS` |
| `SessionMiddleware` | session PHP (`session_start`), helpers de session |
| `TrustedHostMiddleware` | refuse les hôtes non autorisés (403) |
| `RateLimitingMiddleware` | limitation de débit via Redis (nécessite `Security\RateLimiter`) |
| `BackgroundTasksMiddleware` | exécute les tâches après la réponse |

```php
<?php

use Eorbahapi\Middlewares\CORSMiddleware;
use Eorbahapi\Middlewares\TrustedHostMiddleware;

$app->add_middleware(CORSMiddleware::class);
$app->add_middleware(TrustedHostMiddleware::class, [['api.exemple.com']]);
```

`RateLimitingMiddleware` a besoin d'un serveur Redis (connexion paresseuse) et
de l'implémentation `Eorbahapi\Security\RateLimiter` : réservez-le à la
production, avec `maxRequests` et `timeWindow`.

## Interrompre la chaîne

Un middleware peut court-circuiter la route en retournant une réponse :

```php
<?php

use Eorbahapi\Responses\JSONResponse;

final class AuthMiddleware
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($request->getHeader('X-Api-Key') !== 'secret') {
            return new JSONResponse(['detail' => 'Non autorisé'], 401);
        }

        return $next();
    }
}
```

## Réponse et en-têtes

Les middlewares écrivent dans la `Response` partagée. Si le handler retourne une
autre `Response`, `handle()` reporte les en-têtes manquants sur la réponse
finale : CORS, cache et traces survivent.

!!! tip "Ordre CORS"
    Placez `CORSMiddleware` en premier : il doit pouvoir ajouter ses en-têtes
    même quand un middleware d'authentification coupe la chaîne.

## Récapitulatif

- un middleware est un callable `(request, response, next)` ;
- ordre : global → route → handler, puis retour en sens inverse ;
- `add_middleware()` accepte classe, instance et args ;
- les middlewares fournis couvrent CORS, session, hôtes, débit et tâches ;
- un middleware peut retourner une réponse pour court-circuiter ;
- les en-têtes posés sur la réponse partagée sont conservés.

[Suivant : Contrôleurs](controllers.md){ .md-button }
