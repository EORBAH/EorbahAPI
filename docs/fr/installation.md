# Installation

## Ce que vous allez faire

Installer EorbahAPI, comprendre les dépendances qu'il apporte et lancer votre première application.

## Avec Composer

```console
$ composer require eor_bah545/eorbahapi
```

Le paquet déclare trois dépendances importantes :

| Paquet | Rôle |
| --- | --- |
| `svinx/validator` | validation et sérialisation des modèles (inspiré de Pydantic) |
| `twig/twig` | rendu de templates HTML |
| `firebase/php-jwt` | signature et vérification des jetons JWT |

!!! note "PHP 8.1 minimum"
    Le framework s'appuie sur les attributs, les types d'union, les enums et les
    arguments nommés. PHP 8.1 est donc un minimum ; les exemples du cours sont
    testés sur PHP 8.4.

## Structure d'un projet

```
mon-api/
├── public/
│   └── index.php        # point d'entrée HTTP
├── src/                 # vos contrôleurs, modèles, dépendances
├── templates/           # templates Twig (optionnel)
├── tests/               # PHPUnit
└── composer.json
```

Le point d'entrée est minimal :

```php
<?php
// public/index.php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(debug: true);

$app->get('/', fn () => ['message' => 'Bonjour EorbahAPI']);

$app->run();
```

!!! warning "`debug: true` en développement seulement"
    En mode debug, le message des erreurs inattendues est renvoyé au client
    (champ `debug` de la réponse 500). En production, laissez la valeur par
    défaut : le message reste interne.

## Lancer le serveur

Le serveur intégré de PHP suffit pour développer :

```console
$ php -S localhost:8000 -t public
```

```console
$ curl localhost:8000/
{"message":"Bonjour EorbahAPI"}
```

Vérifiez le statut HTTP :

```console
$ curl -i localhost:8000/inconnu
HTTP/1.1 404 Not Found
```

Une route inconnue renvoie `404 Not Found` et un corps `Not Found` (personnalisable avec `default_route()`, voir [Gestion des erreurs](errors.md)).

## Vérifier l'installation

Deux vérifications rapides, sans serveur web :

```php
<?php

require 'vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Testing\TestClient;

$app = new EorbahAPI();
$app->get('/ping', fn () => ['pong' => true]);

// 1. Le client de test (aucun socket, aucun serveur).
$response = (new TestClient($app))->get('/ping');
var_dump($response->status(), $response->json());

// 2. Un runner qui injecte sa propre requête.
$response = $app->handle(new Request(server: [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/ping',
]));
var_dump($response->status, $response->body);
```

Les deux affichent `200` et `{"pong":true}`.

## Dépendances de développement

Pour la suite de tests du cours :

```console
$ composer require --dev phpunit/phpunit
$ ./vendor/bin/phpunit
```

!!! tip "Les exemples sont testés"
    Les fichiers de `docs_src/` sont chargés par `tests/ExamplesTest.php`. Vous
    pouvez les copier dans votre projet : ils sont valides tels quels.

## Récapitulatif

- `composer require eor_bah545/eorbahapi` ;
- un point d'entrée de 10 lignes suffit ;
- `$app->run()` pour le HTTP, `$app->handle($request)` pour tout le reste ;
- `debug: true` en développement, jamais en production.

[Suivant : Premiers pas](first-steps.md){ .md-button }
