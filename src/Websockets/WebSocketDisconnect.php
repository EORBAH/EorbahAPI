<?php

namespace Eorbahapi\Websockets;

use Eorbahapi\Exceptions\WebSocketException;

/**
 * Levée lorsque le pair ferme la connexion WebSocket.
 *
 * Le code est un code de fermeture RFC 6455 (1000 = fermeture normale, 1001 =
 * départ du serveur, 1011 = erreur interne…).
 */
class WebSocketDisconnect extends WebSocketException
{
    public function __construct(
        public readonly int $closeCode = 1000,
        string $reason = '',
    ) {
        parent::__construct(
            $reason !== '' ? $reason : 'WebSocket closed (' . $closeCode . ')',
            $closeCode
        );
    }
}
