# Réponses

## Ce que vous allez apprendre

Choisir ce que renvoie un handler, contrôler statut et en-têtes, servir du HTML,
des redirections et des flux, et changer la classe de réponse par défaut.

## Normalisation des retours

Un handler n'a pas besoin de construire une `Response` :

| Retour | Résultat |
| --- | --- |
| `array` / `object` | corps JSON (`Content-Type: application/json`) |
| `Response` | renvoyée telle quelle |
| `string` | corps texte |
| `'redirect:code:url'` | statut + en-tête `Location` |
| `null` / `bool` | réponse en cours inchangée |

```php
<?php

$app->get('/auto', fn () => ['ok' => true]);                       // {"ok":true}
$app->get('/text', fn () => 'bonjour');                            // bonjour
$app->get('/go', fn () => 'redirect:301:/auto');                   // 301 + Location
```

## Réponses typées

```php
<?php

use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Responses\RedirectResponse;

$app->post('/created', fn () => new JSONResponse(['created' => true], 201, [
    'X-Trace' => 'abc',
]));

$app->get('/html', fn () => new HTMLResponse('<h1>Bonjour</h1>'));

$app->get('/go', fn () => new RedirectResponse('/auto', 307));
```

`HTMLResponse` fixe `Content-Type: text/html; charset=UTF-8` ;
`JSONResponse` fixe `application/json` sauf en-tête fourni.

## Streaming et SSE

```php
<?php

use Eorbahapi\Responses\EventStreamResponse;
use Eorbahapi\Responses\StreamingResponse;

$app->get('/chunks', fn () => new StreamingResponse((static function (): \Generator {
    yield 'a';
    yield 'b';
    yield 'c';
})()));

$app->get('/events', fn () => new EventStreamResponse((static function (): \Generator {
    yield "data: ping\n\n";
})()));
```

`StreamingResponse` envoie chaque `yield` puis vide le tampon ;
`EventStreamResponse` préconfigure le SSE (`text/event-stream`, `no-cache`,
`keep-alive`). Le corps n'est consommé qu'à l'envoi : sous `TestClient`, testez
la sortie avec `expectOutputString()`.

## Fichiers et téléchargements

`FileResponse` sert un fichier (ou un `UploadFile`) avec les en-têtes HTTP
attendus, les requêtes `Range` et les validateurs de cache :

```php
<?php

use Eorbahapi\Request;
use Eorbahapi\Responses\FileResponse;

$app->get('/exports/{id}', function (Request $request, int $id) {
    return new FileResponse(__DIR__ . "/exports/$id.csv", request: $request);
});

$app->get('/rapport', function (Request $request) {
    return FileResponse::download(__DIR__ . '/rapports/2024.csv', 'rapport-2024.csv', $request);
});
```

| Fonctionnalité | Détail |
| --- | --- |
| En-têtes | `Content-Type`, `Content-Length`, `ETag`, `Last-Modified`, `Accept-Ranges` |
| `Range: bytes=0-6` | **206** + `Content-Range`, contenu partiel |
| `If-None-Match` / `If-Modified-Since` | **304** sans corps |
| Plage invalide | **416** + `Content-Range: bytes */taille` |
| `HEAD` | en-têtes seulement, sans corps |
| `download()` | `Content-Disposition: attachment` + nom de fichier |
| `UploadFile` | `FileResponse::fromUploadFile($uploadFile)` |

`EorbahAPI::handle()` rattache automatiquement la requête courante à la
`FileResponse` : `Range` et `If-None-Match` fonctionnent sans passer `request:`.
Le corps est diffusé par morceaux (`send()`), et Swoole utilise `sendfile()`.

!!! note "Tester un fichier"
    `TestResponse` ne contient pas le corps binaire (il est diffusé à l'envoi).
    Vérifiez les en-têtes/statut, ou utilisez `$response->contents()` côté
    `FileResponse` pour lire la tranche.

## Classe de réponse par défaut

Les retours structurés sont rendus par `JSONResponse`. Une autre classe peut
être imposée à toute l'application :

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Responses\JSONResponse;

$app = new EorbahAPI(default_response_class: JSONResponse::class); // défaut
```

La classe reçoit `(mixed $data, int $status, array $headers)` :

```php
<?php

final class EnvelopeResponse extends \Eorbahapi\Response
{
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] ??= 'application/vnd.api+json';

        parent::__construct(json_encode(['data' => $data]), $status, $headers);
    }
}

$app = new EorbahAPI(default_response_class: EnvelopeResponse::class);
$app->get('/items', fn () => ['items' => [1, 2, 3]]);
// {"data":{"items":[1,2,3]}}
```

Le rendu ne s'applique qu'aux valeurs structurées : un scalaire reste un corps
texte, une `Response` retournée garde le contrôle. `$app->response_class`
expose la classe courante, `set_response_class()` la remplace.

## Statut, en-têtes et cookies

```php
<?php

use Eorbahapi\Request;
use Eorbahapi\Response;

$app->get('/custom', function (Request $request, Response $response) {
    $response->status = 202;
    $response->headers['X-Route'] = $request->path();
    $response->cookie('vu', '1', ['secure' => false]);

    return ['accepted' => true];
});
```

Helpers : `status()`, `body()`, `headers()`, `text()`, `set_content_type()`,
`cookie()`, `clearCookie()`, `removeHeader()`.

!!! warning "`Response::header()` est un setter"
    `$response->header('X-Nom', 'valeur')` (et `setHeader()`) posent l'en-tête et
    appellent `header()` immédiatement. En test, préférez
    `$response->headers['X-Nom'] = 'valeur'`.

## Récapitulatif

- un tableau/objet devient du JSON ; un scalaire du texte ;
- `JSONResponse`, `HTMLResponse`, `RedirectResponse`, `StreamingResponse`,
  `EventStreamResponse` couvrent les besoins courants ;
- `default_response_class` remplace la classe de rendu des données ;
- `Response` expose statut, en-têtes, cookies et helpers ;
- les flux s'envoient au `send()`, pas à la construction.

[Suivant : Tâches d'arrière-plan](background-tasks.md){ .md-button }
