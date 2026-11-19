# Installation

## Prérequis

- PHP **8.1+** (attributs, énumérations, types d'union) ;
- Composer ;
- extensions PHP usuelles : `json`, `mbstring`, `fileinfo` (souvent déjà
  présentes) ; l'extension `swoole` est optionnelle (adaptateur serveur).

## Créer le projet

```console
$ mkdir mon-api && cd mon-api
$ composer require eor_bah545/eorbahapi
```

Composer installe aussi les dépendances du framework : `svinx/validator` (la
validation), `twig/twig` (templates), `firebase/php-jwt` (JWT),
`predis/predis` (cache/limitation via Redis).

## Structure minimale

```text
mon-api/
├── composer.json
├── main.php
├── public/
│   └── index.php
└── cache/              (créé à la demande : cache applicatif/Twig)
```

`main.php` :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(
    title: 'Mon API',
    description: 'Exemple d\'installation',
);

$app->get('/', fn () => ['message' => 'Bonjour !']);

$app->run();
```

`public/index.php` :

```php
<?php

require_once dirname(__DIR__) . '/main.php';
```

## Lancer le serveur

```console
$ php -S 127.0.0.1:8000 public/index.php
```

```console
$ curl "http://127.0.0.1:8000/"
{"message":"Bonjour !"}
```

La documentation interactive est disponible immédiatement :

- `http://127.0.0.1:8000/docs` — Swagger UI ;
- `http://127.0.0.1:8000/redoc` — ReDoc ;
- `http://127.0.0.1:8000/openapi.json` — spécification OpenAPI 3.1.

## Vérifier l'installation

```php
<?php

use Eorbahapi\Testing\TestClient;

$client = new TestClient($app);
$response = $client->get('/');

assert(200 === $response->status());
```

Voir [Tests](testing.md) pour `TestClient` et l'intégration PHPUnit.

## Configurer le projet

Les options du constructeur couvrent la documentation, la réponse par défaut et
le cache — voir [Documentation OpenAPI](openapi.md), [Cache](cache.md) et
[Contrats et adaptateurs](contracts.md). Un exemple complet est fourni dans
`main.php` à la racine du dépôt.

!!! note "Sans serveur web"
    `handle(Request): Response` construit la réponse sans l'envoyer : vous
    pouvez l'intégrer à un worker, un runner CLI ou un serveur asynchrone sans
    réécrire l'application — voir [Runners personnalisés](runners.md).

## Récapitulatif

- PHP 8.1+ et Composer suffisent ;
- `composer require eor_bah545/eorbahapi` installe le framework et ses
  dépendances ;
- `main.php` décrit l'application, `public/index.php` la sert ;
- `php -S 127.0.0.1:8000 public/index.php` lance le serveur de développement ;
- `/docs`, `/redoc` et `/openapi.json` sont disponibles sans configuration.

[Suivant : Premiers pas](first-steps.md){ .md-button }
