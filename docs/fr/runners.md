# Runners personnalisés

Une application EorbahAPI n'est pas liée au serveur web : elle reçoit une
requête, construit une réponse, et c'est **vous** qui décidez comment cette
réponse est envoyée. Cette page montre comment piloter la même application
depuis un test, une commande CLI, un worker, PHP-FPM ou un serveur asynchrone
comme Swoole.

## `handle()` ou `run()` ?

Deux points d'entrée, une seule différence : l'envoi.

| Méthode | Signature | Envoie la réponse ? |
| --- | --- | --- |
| `handle()` | `handle(Request $request, ?Response $response = null): Response` | non : retourne l'objet `Response` |
| `run()` | `run(?Request $request = null): void` | oui : `handle()` puis `send()` |

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;

$app = new EorbahAPI();
$app->get('/', fn () => ['ok' => true]);

// 1. Construire la réponse, sans l'envoyer
$response = $app->handle(new Request(server: [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
]), new Response());

// 2. Servir en HTTP : envoie statut, en-têtes et corps
$app->run();
```

`Response::send()` fait exactement trois choses : `http_response_code()`,
`header()` pour chaque en-tête, puis `echo` du corps. C'est cette séparation qui
permet de servir la même application en HTTP, en CLI ou dans un worker.

!!! warning "`run()` écrit vraiment"
    `run()` manipule les en-têtes HTTP et la sortie standard : en CLI, le JSON
    apparaît dans le terminal et `header()` avertit si une sortie a déjà
    commencé. Réservez `run()` au point d'entrée web et utilisez `handle()`
    partout ailleurs.

## Construire une requête à la main

`Eorbahapi\Request` accepte toutes ses sources en paramètres nommés : rien n'est
lu dans les superglobales si vous fournissez une valeur.

| Paramètre | Contenu |
| --- | --- |
| `server` | surcharge de `$_SERVER` (`REQUEST_METHOD`, `REQUEST_URI`…) |
| `query` | surcharge de `$_GET` |
| `postData` | surcharge de `$_POST` |
| `cookies` | surcharge de `$_COOKIE` |
| `files` | surcharge de `$_FILES` |
| `headers` | en-têtes déjà extraits (noms insensibles à la casse) |
| `body` | corps décodé (tableau) ou brut (chaîne JSON) |
| `params` | paramètres de route fournis par le routeur |

```php
<?php

use Eorbahapi\Request;

$request = new Request(
    body: ['title' => 'Relire la doc'],
    server: [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/tasks?dry_run=1',
    ],
    query: ['dry_run' => '1'],
    headers: ['Content-Type' => 'application/json'],
);

$request->method();  // 'POST'
$request->path();    // '/tasks'
$request->body('title'); // 'Relire la doc'
```

`Request::withUri(string $uri): static` retourne une **copie** de la requête
pointant sur une autre URI, sans toucher à l'originale : c'est ce que fait
`mount()` lorsqu'il retire le préfixe avant de confier la requête à la
sous-application.

```php
<?php

use Eorbahapi\Request;

$request = new Request(server: ['REQUEST_URI' => '/static/assets/logo.svg']);

$sub = $request->withUri('/assets/logo.svg');

$sub->path();     // '/assets/logo.svg'
$request->path(); // '/static/assets/logo.svg'
```

## Un runner en quelques lignes

Un runner n'est qu'une boucle : fabriquer des `Request`, appeler `handle()`,
inspecter les `Response`.

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;

$app = new EorbahAPI();
$app->get('/tasks', fn () => ['tasks' => []]);

$requests = [
    new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tasks']),
    new Request(
        server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tasks'],
        body: ['title' => 'Relire la doc'],
    ),
];

foreach ($requests as $request) {
    $response = $app->handle($request, new Response());

    // $response->status(), $response->body$, $response->headers...
}
```

Le même `handle()` sert au `TestClient`, à un worker de file d'attente (une
tâche = une requête fabriquée depuis le message) et à une commande CLI (des
arguments transformés en `query` ou en `body`).

## Servir en HTTP

Le point d'entrée web habituel est `public/index.php`, qui charge `main.php` et
appelle `run()`. Avec le serveur intégré de PHP :

```bash
php -S 127.0.0.1:8000 public/index.php
```

Le script passé à `php -S` reçoit toutes les requêtes : `main.php` construit
l'application à chaque requête, puis `run()` envoie la réponse.

## Le runner ESGI et les adaptateurs

Pour brancher EorbahAPI sur autre chose que PHP-FPM, deux pièces suffisent :

- `EorbahAPI` implémente directement `Eorbahapi\Contracts\ESGIAppInterface`
  (`__invoke(RequestInterface, ResponseInterface)`), sans adaptateur ;
- `Eorbahapi\Server\ESGIAppRunner` choisit un adaptateur de serveur et lui
  transmet l'application, l'hôte, le port et le rechargement.

```php
<?php

use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Server\ESGIAppRunner;

$app = new EorbahAPI();

$runner = new ESGIAppRunner($app, new SwooleServerAdapter(workers: 4));
$runner->run(host: '0.0.0.0', port: 8000, reload: false);

// ou Adaptateur explicite, quatre workers
$runner = new ESGIAppRunner();
$runner->run(
    $app,
    '0.0.0.0',
    8000,
    false,
    new SwooleServerAdapter(workers: 4),
);
```

Sans Swoole, l'adaptateur « une requête » traite simplement la requête courante
du SAPI (PHP-FPM, `php -S`) :

```php
<?php

use Eorbahapi\Adapter\Server\MinimalServerAdapter;
use Eorbahapi\EorbahAPI;

$app = new EorbahAPI();

(new MinimalServerAdapter())->run($app);
// host/port/reload sont ignorés : la requête du SAPI courant est déjà là.
```

| Adaptateur | Rôle |
| --- | --- |
| `MinimalServerAdapter` | une requête par processus, pour les SAPIs classiques |
| `SwooleServerAdapter(int $workers = 1)` | serveur HTTP persistant et concurrent |

`ESGIAppRunner::defaultAdapter()` renvoie `SwooleServerAdapter` si l'extension
`swoole` est chargée, `MinimalServerAdapter` sinon.

`SwooleServerAdapter` transmet le statut, les en-têtes, les cookies
(`Set-Cookie` via l'API Swoole), le corps et les réponses en flux
(`StreamingResponse`) ; une requête `HEAD` n'envoie pas de corps. Les clés
`$request->server` de Swoole (en minuscules) sont normalisées pour que le
routeur fonctionne comme sous PHP-FPM.

Si l'application déclare des routes WebSocket (`$app->websocket(...)`),
l'adaptateur démarre un `Swoole\WebSocket\Server` : **HTTP et WebSocket
partagent le même port** (voir [WebSockets](websockets.md)).

### Journaux façon uvicorn

`SwooleServerAdapter` journalise au format uvicorn :

```console
INFO:     Started server process [1234]
INFO:     Waiting for application startup.
INFO:     Application startup complete.
INFO:     EorbahAPI running on http://0.0.0.0:8000 (Press CTRL+C to quit)
INFO:     WebSocket support enabled on ws://0.0.0.0:8000
INFO:     127.0.0.1:53211 - "GET /health HTTP/1.1" 200 OK
INFO:     ('127.0.0.1', 53212) - "WebSocket /ws/echo" [accepted]
INFO:     connection open
ERROR:    Exception in WebSocket handler: ...
INFO:     connection closed
```

```php
new SwooleServerAdapter(
    workers: 4,
    logLevel: 'info',      // debug, info, warning, error, none
    accessLog: true,       // lignes d'accès HTTP/WebSocket
    logger: null,          // callable (string $level, string $message) alternatif
);
```

Le logger par défaut écrit sur `stderr` ; `logger:` permet de brancher PSR-3,
un fichier, ou un collecteur de tests. `logLevel: 'none'` désactive tout.

!!! note "Workers et rechargement"
    `workers` est propre à Swoole : il fixe le nombre de processus de travail
    (`worker_num`). `reload` n'a de sens que pour un serveur persistant ; il est
    ignoré par l'adaptateur minimal. Le constructeur `ESGIAppRunner` accepte
    aussi l'application et l'adaptateur, puis `app()` / `adapter()` permettent
    de les remplacer.

## Sous-applications montées

`mount()` confie toutes les URI commençant par un préfixe à une autre
application, après retrait du préfixe. L'application montée peut être une
`EorbahAPI`, un `StaticFiles`, un `JsonRPC` ou un callable.

```php
<?php

use Eorbahapi\EorbahAPI;

$admin = new EorbahAPI();
$admin->get('/stats', fn () => ['users' => 3]);

$app->mount('/admin', $admin);

// GET /admin/stats  ->  la sous-application reçoit GET /stats
```

`mounted_apps()` retourne le tableau `[préfixe => application]`. Une
sous-application ne doit jamais appeler `send()` : elle remplit la réponse
partagée, que l'hôte envoie une seule fois.

## JSON-RPC et fichiers statiques

```php
<?php

use Eorbahapi\RPC\JsonRPC;
use Eorbahapi\StaticFiles;

$rpc = new JsonRPC();
$rpc->add_method('ping', fn () => 'pong');

$app->mount('/rpc', $rpc);

$app->mount('/static', new StaticFiles(__DIR__ . '/public'));
```

`JsonRPC` expose les méthodes via le protocole JSON-RPC 2.0 ; `StaticFiles` sert
un répertoire avec cache, compression et extensions autorisées configurables.

## Runner CLI

En CLI, transformez les arguments en `Request` et inspectez la `Response` :

```php
<?php

use Eorbahapi\Request;
use Eorbahapi\Response;

$arguments = $_SERVER['argv'] ?? [];
$path = $arguments[1] ?? '/';

$request = new Request(server: [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => $path,
]);

$response = $app->handle($request, new Response());

echo $response->text(), PHP_EOL;
```

## Récapitulatif

- `handle(Request, ?Response)` construit et retourne la réponse, sans envoi ;
- `run(?Request)` appelle `handle()` puis `send()` : c'est le point d'entrée HTTP ;
- `new Request(...)` accepte `body`, `params`, `server`, `query`, `postData`,
  `cookies`, `files`, `headers` — tout est injectable ;
- `Request::withUri($uri)` donne une copie de la requête sur une autre URI ;
- `EorbahAPI` (via `ESGIAppInterface`) + `ESGIAppRunner` branchent l'application
  sur `MinimalServerAdapter` ou `SwooleServerAdapter` ;
- `mount()` compose les applications (sous-app, `StaticFiles`, `JsonRPC`,
  callable) sans modifier le pipeline ;
- `handle()` couvre tests, CLI, workers et montage : un seul point d'entrée.

[Suivant : Journalisation](logger.md){ .md-button }
