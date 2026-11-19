# Premiers pas

## Ce que vous allez apprendre

Créer une application, enregistrer des routes, renvoyer du JSON et comprendre les
deux points d'entrée : `run()` et `handle()`.

## Une application minimale

Tout tient dans un fichier `main.php` :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;

$app = new EorbahAPI(
    title: 'Premiers pas',
    description: 'Mon premier service',
);

$app->get('/', function () {
    return ['message' => 'Bonjour EorbahAPI'];
});

$app->get('/health', function () {
    return ['status' => 'ok'];
});

$app->run();
```

Le constructeur accepte les métadonnées de l'application (`title`,
`description`, `version`…) : elles alimentent la documentation OpenAPI et les
pages d'erreur.

!!! tip "La documentation est déjà là"
    Toute application expose automatiquement `/openapi.json`, `/docs` (Swagger
    UI) et `/redoc`. Les adresses et métadonnées se règlent dans le
    constructeur — voir [Documentation OpenAPI](openapi.md).

## Lancer le serveur

```console
$ php -S 127.0.0.1:8000 public/index.php
```

`public/index.php` contient simplement :

```php
<?php

require_once dirname(__DIR__) . '/main.php';
```

```console
$ curl "http://127.0.0.1:8000/health"
{"status":"ok"}
```

## Ce que renvoie un handler

Un handler peut retourner plusieurs formes ; le framework les normalise en
`Response`.

| Retour | Résultat |
| --- | --- |
| `array` / `object` | corps JSON, `Content-Type: application/json` |
| `Response` | la réponse telle quelle (statut et en-têtes compris) |
| `string` | corps texte |
| `'redirect:code:url'` | redirection : statut + en-tête `Location` |
| `null` / `true` / `false` | la réponse en cours, inchangée |

```php
<?php

$app->get('/auto', fn () => ['ok' => true]);                       // {"ok":true}
$app->get('/text', fn () => 'bonjour');                            // bonjour
$app->get('/created', fn () => new JSONResponse(['ok' => true], 201));
$app->get('/go', fn () => 'redirect:301:/auto');
```

Pour un contrôle fin (statut, en-têtes, cookies), retournez un objet de
`Eorbahapi\Responses` — voir [Réponses](responses.md).

## `run()` et `handle()`

```php
<?php

$app->run();                                   // lit la requête réelle, envoie la réponse

$response = $app->handle(new Request(server: [  // construit la réponse, ne l'envoie pas
    'REQUEST_METHOD' => 'GET',
    'REQUEST_URI' => '/',
]));

$app->run($request);                           // lit une requête fournie et l'envoie
```

`handle()` est le cœur du framework : c'est lui qui rend l'application testable
sans socket HTTP et qui permet de brancher un autre transport
([Runners personnalisés](runners.md)).

```php
<?php

use Eorbahapi\Testing\TestClient;

$client = new TestClient($app);
$response = $client->get('/health');

$response->status();   // 200
$response->json();     // ['status' => 'ok']
```

## Récapitulatif

- une application est un objet `EorbahAPI` ;
- `$app->get($chemin, $handler)` enregistre une route (`post`, `put`, `patch`,
  `delete`, `head`, `options` fonctionnent de la même façon) ;
- un handler retourne des données, une `Response` ou une chaîne ;
- `run()` sert le HTTP, `handle()` sert tout le reste (tests, CLI, runners) ;
- la documentation Swagger/ReDoc est disponible sans configuration.

[Suivant : Routage](routing.md){ .md-button }
