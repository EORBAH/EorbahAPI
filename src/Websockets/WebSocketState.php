<?php

namespace Eorbahapi\Websockets;

/**
 * États d'une connexion WebSocket (RFC 6455 / API FastAPI).
 */
enum WebSocketState: string
{
    case CONNECTING = 'connecting';
    case OPEN = 'open';
    case CLOSING = 'closing';
    case CLOSED = 'closed';
}
