# Premiers pas

## Ce que vous allez apprendre

Créer une application, enregistrer des routes, retourner du JSON, choisir le
statut HTTP — et comprendre les deux points d'entrée : `run()` et `handle()`.

## Une application minimale

`docs_src/FirstStepsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;

final class FirstStepsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI(title: 'Premiers pas', description: 'Mon premier service');

        $app->get('/', function () {
            return ['message' => 'Bonjour EorbahAPI'];
        });

        $app->get('/health', function () {
            return ['status' => 'ok'];
        });

        return $app;
    }
}
```

Deux routes, deux handlers. Le constructeur accepte un titre, une description et
un mode `debug` : ces informations servent à la documentation et au rendu des
erreurs.

## Ce que renvoie un handler

Un handler peut retourner :

| Type retourné | Résultat |
| --- | --- |
| `array` / `object` | corps JSON (`Content-Type` non forcé) |
| `Response` | la réponse telle quelle |
| `string` | corps texte |
| `'redirect:code:url'` | redirection (statut + en-tête `Location`) |
| `null` / `true` | la réponse en cours, inchangée |

```php
$app->get('/auto', fn () => ['ok' => true]);                       // {"ok":true}
$app->get('/text', fn () => 'bonjour');                            // bonjour
$app->get('/created', fn () => new JSONResponse(['ok' => true], 201));
$app->get('/go', fn () => 'redirect:301:/auto');
```

## `run()` et `handle()`

```php
$app->run();                                   // lit la requête réelle, envoie la réponse
$response = $app->handle(new Request(server: [
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
]));                                           // construit la réponse, ne l'envoie pas
$app->run($request);                           // lit une requête fournie, envoie la réponse
```

`handle()` est le cœur du framework : c'est lui qui permet de tester
l'application sans serveur HTTP, ou de la brancher sur un autre transport
([Runners personnalisés](runners.md)).

## Les scénarios de l'exemple

```php
<?php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/', json: ['message' => 'Bonjour EorbahAPI']),
        self::scenario('GET', '/health', json: ['status' => 'ok']),
        self::scenario('GET', '/inconnu', status: 404, contains: 'Not Found'),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /` | 200 | `{"message":"Bonjour EorbahAPI"}` |
| `GET /health` | 200 | `{"status":"ok"}` |
| `GET /inconnu` | 404 | `Not Found` |

## Exécuter l'exemple vous-même

```php
<?php

require 'vendor/autoload.php';

use Eorbahapi\Examples\FirstStepsExample;
use Eorbahapi\Testing\TestClient;

$client = new TestClient(FirstStepsExample::app());

$response = $client->get('/');
echo $response->status();       // 200
echo $response->text();         // {"message":"Bonjour EorbahAPI"}
```

!!! tip "Deux façons de servir"
    - `php -S localhost:8000 -t public` pour le navigateur et `curl` ;
    - `TestClient` pour les tests : pas de port, pas de socket, pas de latence.

## Récapitulatif

- une application est un objet `EorbahAPI` ;
- `$app->get($chemin, $handler)` enregistre une route (idem `post`, `put`, `patch`, `delete`, `head`, `options`) ;
- un handler retourne des données, un `Response` ou une chaîne ;
- `run()` sert le HTTP, `handle()` sert tout le reste.

[Suivant : Routage](routing.md){ .md-button }
