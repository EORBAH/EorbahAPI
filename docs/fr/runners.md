# Runners personnalisés

## Ce que vous allez apprendre

Piloter une application EorbahAPI depuis autre chose qu'un serveur web :
distinguer `handle()` (qui **construit** la réponse) de `run()` (qui l'**envoie**),
construire une `Eorbahapi\Request` complète à la main (`server`, `query`, `post`,
`cookies`, `files`, `headers`, `body`), rediriger une requête vers une autre URI
avec `Request::withUri()`, utiliser `APIRouter::dispatch()` pour un routeur
autonome, et brancher le tout dans un worker, une commande CLI, un test ou une
autre application.

## Le code

`docs_src/RunnersExample.php` enregistre deux routes, puis un « runner » qui
traite deux requêtes sans passer par le serveur web :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

final class RunnersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/tasks', fn () => ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]);
        $app->post('/tasks', fn (Request $request) => new JSONResponse(
            ['created' => $request->body('title')],
            201
        ));

        return $app;
    }

    /**
     * Un « runner » qui traite des requêtes sans passer par le serveur web.
     *
     * @return list<Response>
     */
    public static function runner(): array
    {
        $app = self::app();

        $requests = [
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tasks']),
            new Request(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tasks'],
                body: ['title' => 'Relire la doc'],
                headers: ['Content-Type' => 'application/json'],
            ),
        ];

        $responses = [];
        foreach ($requests as $request) {
            $responses[] = $app->handle($request, new Response());
        }

        return $responses;
    }
}
```

Les deux points d'entrée de l'application :

| Méthode | Signature | Envoie la réponse ? |
| --- | --- | --- |
| `handle()` | `handle(Request $request, ?Response $response = null): Response` | non : retourne l'objet `Response` |
| `run()` | `run(?Request $request = null): void` | oui : `handle()` puis `send()` |

`send()` fait exactement trois choses : `http_response_code()`, `header()` pour
chaque en-tête, puis `echo` du corps. C'est cette séparation qui permet de servir
la même application en HTTP, en CLI ou dans un worker.

Le constructeur `new Request(...)` accepte toutes les sources de données, chacune
injectable indépendamment des superglobales :

| Paramètre | Contenu |
| --- | --- |
| `server` | surcharge de `$_SERVER` (`REQUEST_METHOD`, `REQUEST_URI`…) |
| `query` | surcharge de `$_GET` |
| `postData` | surcharge de `$_POST` |
| `cookies` | surcharge de `$_COOKIE` |
| `files` | surcharge de `$_FILES` |
| `headers` | en-têtes déjà extraits (noms insensibles à la casse) |
| `body` | corps décodé (tableau) ou brut (chaîne JSON) |
| `params` | paramètres de route, positionnels |

Deux outils complètent le tableau. `Request::withUri(string $uri): static`
retourne une **copie** de la requête pointant sur une autre URI, sans toucher à
l'originale : c'est ce que fait `mount()` quand il retire le préfixe avant de
confier la requête à la sous-application.

```php
$request = new Request(server: ['REQUEST_URI' => '/static/assets/logo.svg']);

$sub = $request->withUri('/assets/logo.svg');

$sub->path();     // '/assets/logo.svg'
$request->path(); // '/static/assets/logo.svg'
```

`APIRouter::dispatch()` exécute une route sans application autour :

```php
$router = new APIRouter();
$router->get('/users/{id}', fn (int $id) => ['id' => $id]);

$response = $router->dispatch(new Request(server: ['REQUEST_URI' => '/users/7']));
```

Il retourne une `Response`, ou `null` quand aucune route ne correspond — à vous
de décider quoi en faire (404, journalisation, nouvel essai). Sans
`REQUEST_METHOD`, la requête est considérée comme un `GET`.

## Ce qui se passe

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/tasks', json: ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]),
        self::scenario('POST', '/tasks', ['json' => ['title' => 'Relire la doc']], status: 201, json: ['created' => 'Relire la doc']),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /tasks` | 200 | `{"tasks":[{"id":1,"title":"Écrire la doc"}]}` |
| `POST /tasks` · `{"title":"Relire la doc"}` | 201 | `{"created":"Relire la doc"}` |

!!! warning "`run()` envoie vraiment"
    `$app->run()` écrit le corps sur la sortie standard et manipule les en-têtes
    HTTP : en CLI, le JSON apparaît dans le terminal et `header()` avertit si une
    sortie a déjà commencé. Réservez `run()` au point d'entrée web (`public/index.php`)
    et utilisez `handle()` partout ailleurs. Dans une sous-application montée,
    `process()` ne doit jamais appeler `send()` : la réponse partagée est
    remplie, puis envoyée une seule fois par l'application hôte.

!!! tip "Quatre usages, une seule méthode"
    `handle()` couvre le test (`TestClient` l'appelle), le worker de file
    d'attente (une tâche = une `Request` fabriquée depuis le message), la
    commande CLI (des arguments transformés en `query` ou en `body`) et le
    montage dans une autre application (`withUri()` retire le préfixe). Le corps
    de la réponse est récupérable via `$response->body`, `$response->text()` ou,
    si c'est du JSON, `$response->body` décodé par vos soins.

## Récapitulatif

- `handle(Request, ?Response)` construit et retourne la réponse, sans envoi ;
- `run(?Request)` appelle `handle()` puis `send()` : c'est le point d'entrée HTTP ;
- `new Request(...)` accepte `body`, `params`, `server`, `query`, `postData`,
  `cookies`, `files`, `headers` — tout est injectable ;
- `Request::withUri($uri)` donne une copie de la requête sur une autre URI, ce
  que `mount()` utilise pour retirer le préfixe des sous-applications ;
- `APIRouter::dispatch(Request, ?Response)` exécute une route seule et retourne
  `null` en l'absence de correspondance ;
- CLI, worker, test, montage : quatre runners, une seule méthode à appeler.

[Suivant : Référence API](reference.md){ .md-button }
