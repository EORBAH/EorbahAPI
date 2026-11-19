# JSON-RPC

## Ce que vous allez apprendre

Exposer des méthodes JSON-RPC 2.0 en montant un serveur `JsonRPC`, et les appeler
avec un simple POST JSON.

## Monter un serveur JSON-RPC

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\RPC\JsonRPC;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class CreerUtilisateur extends BaseModel
{
    #[Field(min_length: 3)]
    public string $nom;
}

$rpc = new JsonRPC();
$rpc->add_method('user.create', function (#[Body] CreerUtilisateur $user) {
    return ['id' => 1, 'nom' => $user->nom];
});
$rpc->add_method('ping', fn () => 'pong');

$app = new EorbahAPI();
$app->mount('/rpc', $rpc);

$app->run();
```

## Appeler une méthode

```console
$ curl -X POST "http://127.0.0.1:8000/rpc" \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"ping","id":1}'
{"jsonrpc":"2.0","result":"pong","id":1}
```

Avec paramètres nommés :

```console
$ curl -X POST "http://127.0.0.1:8000/rpc" \
    -H 'Content-Type: application/json' \
    -d '{"jsonrpc":"2.0","method":"user.create","params":{"nom":"Oumar"},"id":2}'
{"jsonrpc":"2.0","result":{"id":1,"nom":"Oumar"},"id":2}
```

Les `params` deviennent le corps de la requête : les attributs (`#[Body]`,
`#[Query]`…) du callback fonctionnent comme dans une route HTTP.

## Erreurs JSON-RPC

| Code | Cas |
| --- | --- |
| `-32600` | requête invalide (corps ou `jsonrpc` incorrect) |
| `-32601` | méthode inconnue |
| `-32602` | paramètres invalides (validation) |
| `-32603` | erreur interne |

```json
{"jsonrpc":"2.0","error":{"code":-32601,"message":"Method not found"},"id":3}
```

## Notifications

Sans `id`, la requête est une notification : le serveur répond tout de même avec
`id: null` dans la forme actuelle. Si vous avez besoin de la sémantique stricte
« aucune réponse », interceptez ces requêtes en amont ou adaptez le serveur.

## Options

Le constructeur accepte un tableau d'options :

```php
<?php

$rpc = new JsonRPC([
    'name' => 'Outils',
    'description' => 'Serveur JSON-RPC de démonstration',
]);
```

!!! tip "Montage et test"
    `JsonRPC` est une application montée : le préfixe est retiré avant qu'elle
    ne traite la requête, et `TestClient` peut l'appeler sans socket.

```php
<?php

use Eorbahapi\Testing\TestClient;

$client = new TestClient($app);
$response = $client->post('/rpc', ['json' => [
    'jsonrpc' => '2.0',
    'method' => 'ping',
    'id' => 1,
]]);

$response->json();   // ['jsonrpc' => '2.0', 'result' => 'pong', 'id' => 1]
```

## Récapitulatif

- `new JsonRPC()` crée un serveur montable ;
- `add_method($nom, $callback)` enregistre une méthode ;
- `$app->mount('/rpc', $rpc)` l'expose sur le préfixe ;
- les `params` sont résolus comme un corps HTTP (attributs Svinx compris) ;
- les erreurs suivent les codes JSON-RPC 2.0.

[Suivant : Tests](testing.md){ .md-button }
