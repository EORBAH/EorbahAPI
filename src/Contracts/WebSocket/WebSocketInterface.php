<?php

namespace Eorbahapi\Contracts\WebSocket;

/**
 * Connexion WebSocket complète : envoi, réception et contexte.
 */
interface WebSocketInterface extends WebSocketSendMessage, WebSocketReceiveMessage, WebSocketScope
{
}
