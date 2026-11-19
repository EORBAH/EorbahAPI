# WebSockets

## Ce que vous allez apprendre

Déclarer une route WebSocket, servir une connexion et échanger des messages avec
le serveur intégré, sans passer par le pipeline HTTP.

## Déclarer une route

Les routes WebSocket sont stockées séparément des routes HTTP :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Websockets\WebSocket;

$app = new EorbahAPI('WebSocket');

$app->websocket('/ws/rooms/{room}', static function (WebSocket $websocket): void {
    $websocket->send('bienvenue dans ' . $websocket->params()['room']);

    while (($message = $websocket->receive()) !== null) {
        $websocket->send('écho : ' . $message);
    }
});

$app->run(); // le pipeline HTTP ignore les routes WebSocket
```

`websocket_routes()` expose le routeur, utile pour un serveur dédié :

```php
<?php

$app->websocket_routes()->count();          // 1
$app->websocket_routes()->match('/ws/rooms/salon'); // handler + params
```

## Le handler

Le handler reçoit un objet `WebSocket` et peut :

| Membre | Rôle |
| --- | --- |
| `send(string)` | envoie un message texte |
| `send_json(mixed)` | envoie une valeur JSON |
| `send_bytes(string)` | envoie un message binaire |
| `receive()` | message suivant, ou `null` en fin de flux |
| `receive_json()` | message suivant décodé en JSON |
| `receive_bytes()` | message binaire suivant |
| `close(int $code, string $reason)` | ferme la connexion |
| `state()` | état (`WebSocketState`) |
| `path()`, `params()`, `query()`, `headers()` | contexte de la connexion |
| `client()`, `server()` | adresses, si connues |

Une fermeture par le pair lève `WebSocketDisconnect` (avec son `closeCode`).
`client()` et `server()` valent `null` pour le serveur intégré.

## HTTP et WebSocket sur le même port

Avec Swoole, aucun deuxième port n'est nécessaire : `SwooleServerAdapter`
détecte les routes WebSocket de l'application et démarre un
`Swoole\WebSocket\Server`. Le **même `host:port`** sert le HTTP et les
WebSockets.

```php
<?php

use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Server\ESGIAppRunner;
use Eorbahapi\Websockets\WebSocket;

$app = new EorbahAPI();

$app->get('/health', fn () => ['status' => 'ok']);

$app->websocket('/ws/echo', static function (WebSocket $websocket): void {
    while (($message = $websocket->receive()) !== null) {
        $websocket->send('écho : ' . $message);
    }
});

(new ESGIAppRunner())->run(
    $app,
    '0.0.0.0',
    8000,
    false,
    new SwooleServerAdapter(workers: 4),
);
```

```console
$ curl "http://127.0.0.1:8000/health"
{"status":"ok"}

$ wscat -c ws://127.0.0.1:8000/ws/echo
Connected
> bonjour
écho : bonjour
```

`php main.php` démarre exactement ce serveur mixte. Le handler WebSocket peut
rester bloquant (`while ($ws->receive())`) : Swoole exécute chaque connexion dans
une coroutine, et `receive()` attend les trames envoyées par `on('message')`.

!!! note "Sans Swoole"
    PHP-FPM et `php -S` ne savent pas faire l'upgrade WebSocket. Utilisez alors
    `WebSocketServer`, qui écoute sur **son propre port** (voir ci-dessous) :
    HTTP et WebSocket ne peuvent pas partager le même port sans serveur
    asynchrone.

## Servir les connexions en autonome

Le serveur intégré `WebSocketServer` est basé sur les streams PHP : il fait la
poignée de main HTTP, choisit la route et confie la connexion au handler.

```php
<?php

use Eorbahapi\Websockets\WebSocketServer;

(new WebSocketServer('127.0.0.1', 8081))->run($app);
// ou : ->run($app->websocket_routes());
```

`main.php` fournit un mode dédié :

```console
$ php main.php --websocket
Serveur WebSocket : ws://127.0.0.1:8081/ws/echo
```

Côté client, n'importe quel client WebSocket fait l'affaire : `wscat`,
`websocat`, `new WebSocket('ws://127.0.0.1:8081/ws/echo')` en JavaScript.

## Le protocole

- **poignée de main** : `101 Switching Protocols`, `Sec-WebSocket-Accept`
  calculé depuis la clé client (RFC 6455) ;
- **trames** : texte `0x1`, binaire `0x2`, continuation `0x0`, ping `0x9`,
  pong `0xA`, close `0x8` ;
- **fragmentation** : les messages fragmentés sont réassemblés avant d'être
  rendus au handler ;
- **ping/pong** : un `ping` reçu est acquitté automatiquement ;
- **états** : `WebSocketState::{CONNECTING, OPEN, CLOSING, CLOSED}`.

!!! warning "Serveur intégré mono-tâche"
    `WebSocketServer` traite une connexion à la fois et bloque pendant le
    handler. Pour de la concurrence, utilisez Swoole/RoadRunner et branchez la
    même logique de handler.

!!! tip "Tester sans réseau"
    Le test `tests/WebSocketTest.php` utilise `stream_socket_pair()` : la
    poignée de main et les trames sont vérifiées sans port ni fork.

## Récapitulatif

- `$app->websocket('/ws/{room}', handler)` enregistre une route WebSocket ;
- le handler manipule un `WebSocket` (`send`, `receive`, `close`, contexte) ;
- `WebSocketServer` fait la poignée de main et la boucle d'écoute ;
- `php main.php --websocket` sert l'exemple d'écho ;
- le serveur intégré est synchrone : un handler long bloque les autres clients.

[Suivant : Gestion des erreurs](errors.md){ .md-button }
