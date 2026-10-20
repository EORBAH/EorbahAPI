# Installation

## What you will do

Install EorbahAPI, understand the dependencies it brings and run your first application.

## With Composer

```console
$ composer require eor_bah545/eorbahapi
```

The package declares three important dependencies:

| Package | Role |
| --- | --- |
| `svinx/validator` | model validation and serialization (inspired by Pydantic) |
| `twig/twig` | rendering of HTML templates |
| `firebase/php-jwt` | signing and verification of JWT tokens |

!!! note "PHP 8.1 minimum"
    The framework relies on attributes, union types, enums and named
    arguments. PHP 8.1 is therefore a minimum; the examples in the course are
    tested on PHP 8.4.

## Project structure

```
mon-api/
├── public/
│   └── index.php        # point d'entrée HTTP
├── src/                 # vos contrôleurs, modèles, dépendances
├── templates/           # templates Twig (optionnel)
├── tests/               # PHPUnit
└── composer.json
```

The entry point is minimal:

```php
<?php
// public/index.php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(debug: true);

$app->get('/', fn () => ['message' => 'Bonjour EorbahAPI']);

$app->run();
```

!!! warning "`debug: true` in development only"
    In debug mode, the message of unexpected errors is sent back to the client
    (`debug` field of the 500 response). In production, leave the default
    value: the message stays internal.

## Running the server

PHP's built-in server is enough for development:

```console
$ php -S localhost:8000 -t public
```

```console
$ curl localhost:8000/
{"message":"Bonjour EorbahAPI"}
```

Check the HTTP status:

```console
$ curl -i localhost:8000/inconnu
HTTP/1.1 404 Not Found
```

An unknown route returns `404 Not Found` and a `Not Found` body (customizable with `default_route()`, see [Error handling](errors.md)).

## Verifying the installation

Two quick checks, without a web server:

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

Both display `200` and `{"pong":true}`.

## Development dependencies

For the course's test suite:

```console
$ composer require --dev phpunit/phpunit
$ ./vendor/bin/phpunit
```

!!! tip "The examples are tested"
    The files in `docs_src/` are loaded by `tests/ExamplesTest.php`. You
    can copy them into your project: they are valid as is.

## Summary

- `composer require eor_bah545/eorbahapi`;
- a 10-line entry point is enough;
- `$app->run()` for HTTP, `$app->handle($request)` for everything else;
- `debug: true` in development, never in production.

[Next: First steps](first-steps.md){ .md-button }
