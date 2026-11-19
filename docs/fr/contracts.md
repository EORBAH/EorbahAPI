# Contrats et adaptateurs

## Ce que vous allez apprendre

Ce que figent les contrats de `Eorbahapi\Contracts`, comment les adaptateurs les
relient à `EorbahAPI` sans modifier le framework, et comment brancher
l'application sur un serveur.

## Les contrats

Les contrats décrivent la surface publique sans la changer. Les interfaces
historiques en **héritent**, donc le code existant les respecte déjà.

| Contrat | Rôle |
| --- | --- |
| `RequestInterface` | méthode, chemin, query, en-têtes, corps, attributs |
| `ResponseInterface` | statut, corps, en-têtes, envoi |
| `ESGIAppInterface` | application appelable `(request, response) => response` |
| `MiddlewareInterface` | middleware `(request, response, next)` |
| `DependencyInterface` | dépendances `#[Depends]` |
| `TestClientInterface` | client de test HTTP |
| `ServerAdapterInterface` | adaptateur serveur (`run(app, host, port)`) |
| `CacheAdapterInterface` | cache + nom du pilote |
| `UploadFileInterface` | fichier téléversé |
| `WebSocket\WebSocketInterface` | connexion WebSocket |

Les interfaces historiques concernées :

- `Eorbahapi\Middlewares\MiddlewareInterface` ;
- `Eorbahapi\Dependency\DependencyInterface` ;
- `Eorbahapi\Testing\TestClientInterface`.

Elles étendent les contrats correspondants : une classe existante est déjà un
`MiddlewareInterface`/`DependencyInterface` au sens des contrats.

## Adaptateurs HTTP

`EorbahAPI` implémente directement `ESGIAppInterface` : il s'appelle comme une
application et remplit la réponse fournie. `RequestAdapter` et `ResponseAdapter`
exposent les contrats demandés par les serveurs :

```php
<?php

use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;

$app = new EorbahAPI();

$response = $app(
    new RequestAdapter(new Request(server: [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/articles',
    ])),
    ResponseAdapter::create(),
);

$response->status();   // int
$response->text();     // corps
```

## Lancer un serveur

`ESGIAppRunner` choisit l'adaptateur : Swoole si l'extension est présente, sinon
l'adaptateur « une requête » pour PHP-FPM / `php -S`.

```php
<?php

use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\Server\ESGIAppRunner;

$runner = new ESGIAppRunner($app);
$runner->run(host: '0.0.0.0', port: 8000);

// adaptateur explicite :
$runner->run($app, '0.0.0.0', 8000, false, new SwooleServerAdapter(workers: 4));
```

`SwooleServerAdapter` vérifie la présence de l'extension et échoue avec un
message explicite sinon ; `MinimalServerAdapter` traite la requête courante et
envoie la réponse.

## Adaptateurs de cache et de fichiers

```php
<?php

use Eorbahapi\Adapter\Cache\MemoryCacheAdapter;
use Eorbahapi\Adapter\Datastructures\UploadFileAdapter;

$cache = new MemoryCacheAdapter();
$cache->remember('clé', fn () => 'valeur');

$fichier = new UploadFileAdapter($uploadFile);
$fichier->getFilename();
```

## Tâches d'arrière-plan

`BackgroundTasks` + `BackgroundTasksMiddleware` exécutent des callables après la
réponse ; le sujet est détaillé dans
[Tâches d'arrière-plan](background-tasks.md).

## Utilitaires

`Utils::getRoutePath('/users/{id}', ['id' => 7])` reconstruit un chemin,
`Status` fournit les constantes HTTP — voir
[Utilitaires et statuts](utilities.md).

!!! tip "Étendre sans casser"
    Un nouveau serveur n'a besoin que d'implémenter `ServerAdapterInterface` et
    d'appeler l'application. Les contrats évitent de dépendre des classes
    concrètes.

## Récapitulatif

- `Eorbahapi\Contracts` fige la surface publique ;
- les interfaces historiques en héritent, sans modification d'`EorbahAPI` ;
- `EorbahAPI` implémente `ESGIAppInterface` : aucun adaptateur d'application n'est nécessaire ;
- `ESGIAppRunner` + `ServerAdapterInterface` lancent le transport ;
- les adaptateurs de cache et de fichiers sont fournis.

[Suivant : WebSockets](websockets.md){ .md-button }
