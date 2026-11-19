<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Websockets\WebSocket;

/**
 * Route WebSocket : la connexion est décrite en dehors du pipeline HTTP.
 *
 * Le handler reçoit un `WebSocket` ; il peut lire les paramètres du chemin,
 * envoyer et recevoir des messages. Le serveur est lancé par
 * `Eorbahapi\Websockets\WebSocketServer`.
 */
final class WebsocketExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI('WebSocket');

        $app->websocket('/ws/rooms/{room}', static function (WebSocket $websocket): void {
            $websocket->send_json([
                'room' => $websocket->params()['room'],
                'message' => 'bienvenue',
            ]);

            while (($message = $websocket->receive()) !== null) {
                $websocket->send('écho : ' . $message);
            }
        });

        // Route HTTP d'introspection : elle prouve que la route WS est bien enregistrée.
        $app->get('/ws/info', fn (): array => [
            'routes' => $app->websocket_routes()->count(),
        ]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/ws/info', json: ['routes' => 1]),
        ];
    }
}
