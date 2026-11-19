# EorbahAPI

**Framework web PHP moderne, rapide et typé, pour construire des API.**

EorbahAPI réunit l'essentiel de FastAPI dans l'écosystème PHP :

- **des routes déclaratives** — `#[Get]`, `#[Post]`, `#[Query]`, `#[Body]`, `#[Path]`, `#[Header]`, `#[Cookie]`, `#[File]`… ou simplement `$app->get()` ;
- **des paramètres typés et validés** — validation par [`Svinx\Validator`](https://github.com/svinx-org/validator), inspirée de Pydantic (contraintes `ge`, `le`, `min_length`, `pattern`, `choices`, modèles imbriqués, sérialisation) ;
- **une documentation OpenAPI automatique** — Swagger UI, ReDoc et `openapi.json` générés depuis les mêmes déclarations ;
- **un pipeline testable** — `EorbahAPI::handle(Request): Response` construit la réponse sans l'envoyer, ce qui permet de brancher PHP-FPM, un runner CLI, Swoole… ou le `TestClient` ;
- **les briques du quotidien** — cache fichier, tâches d'arrière-plan, WebSockets, fichiers statiques, templates Twig, JSON-RPC, sécurité, middlewares ;
- **des contrats stables** — `Eorbahapi\Contracts` fige la surface publique et les adaptateurs relient l'application aux serveurs.

## Démarrage rapide

`main.php` :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;
use Eorbahapi\Params\Query;

$app = new EorbahAPI(
    title: 'Mon API',
    description: 'Première application EorbahAPI',
);

$app->get('/items/{id}', function (
    #[Path(gte: 1)] int $id,
    #[Query(gte: 1, le: 100)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

Lancement :

```console
$ php -S 127.0.0.1:8000 public/index.php
```

```console
$ curl "http://127.0.0.1:8000/items/7?limit=3"
{"id":7,"limit":3}
```

Et la documentation interactive est disponible immédiatement :

- `http://127.0.0.1:8000/docs` — Swagger UI pour essayer les routes ;
- `http://127.0.0.1:8000/redoc` — ReDoc pour la lecture ;
- `http://127.0.0.1:8000/openapi.json` — la spécification OpenAPI 3.1.

## Un cours, pas une liste de fonctions

Ce site est un **cours progressif**, dans l'esprit de la documentation de FastAPI :
chaque chapitre introduit une notion, montre du code réel et explique ce qui se
passe, requête par requête. Les pages de référence complètent le cours pour les
recherches ponctuelles.

## Par où commencer ?

| Si vous voulez… | Lisez |
| --- | --- |
| installer le framework | [Installation](installation.md) |
| écrire votre première API | [Premiers pas](first-steps.md) |
| comprendre le routage | [Routage](routing.md) |
| valider les paramètres | [Paramètres de requête](query-params.md) et [Validations](validations.md) |
| recevoir du JSON | [Corps de requête](request-body.md) |
| renvoyer du JSON/HTML/flux | [Réponses](responses.md) |
| exécuter du code après la réponse | [Tâches d'arrière-plan](background-tasks.md) |
| sécuriser une route | [Sécurité](security.md) |
| documenter et essayer l'API | [Documentation OpenAPI](openapi.md) |
| accélérer l'application | [Cache](cache.md) |
| brancher un serveur | [Runners personnalisés](runners.md) et [Contrats et adaptateurs](contracts.md) |
| ouvrir une connexion WebSocket | [WebSockets](websockets.md) |
| tester votre application | [Tests](testing.md) |

## Les grandes briques

| Brique | Rôle | Point d'entrée |
| --- | --- | --- |
| Routage | routes statiques et paramétrées, priorités | `EorbahAPI`, `APIRouter` |
| Validation | modèles et contraintes Svinx | `Svinx\Validator` |
| Documentation | OpenAPI, Swagger UI, ReDoc | `Eorbahapi\Openapi` |
| Cache | fichiers PHP, mémoire, Redis | `Eorbahapi\Cache` |
| Tâches | exécution après réponse | `Eorbahapi\BackgroundTasks` |
| WebSockets | RFC 6455, routes et serveur | `Eorbahapi\Websockets` |
| Contrats | interfaces stables + adaptateurs | `Eorbahapi\Contracts`, `Eorbahapi\Adapter` |
| Serveurs | PHP-FPM, `php -S`, Swoole | `Eorbahapi\Server`, `Eorbahapi\Adapter\Server` |
| Sécurité | Bearer, Basic, API keys, OAuth2, JWT | `Eorbahapi\Security` |
| Tests | client HTTP sans socket | `Eorbahapi\Testing\TestClient` |

## Compatibilité

- **PHP 8.1+** (énumérations, attributs, fibres) ;
- dépendances Composer : `svinx/validator`, `twig/twig`, `firebase/php-jwt`, `predis/predis` ;
- aucun envoi implicite depuis `handle()` : les runners décident du transport.

[Suivant : Installation](installation.md){ .md-button }
