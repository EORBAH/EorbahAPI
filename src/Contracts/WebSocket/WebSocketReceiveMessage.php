<?php

namespace Eorbahapi\Contracts\WebSocket;

/**
 * Réception de messages sur une connexion WebSocket.
 *
 * `receive()` retourne null lorsque le pair a fermé la connexion ; une fermeture
 * explicite lève `Eorbahapi\Websockets\WebSocketDisconnect`.
 */
interface WebSocketReceiveMessage
{
    /** Message texte/binaire suivant, ou null en fin de flux. */
    public function receive(): ?string;

    /** Message suivant décodé en JSON. */
    public function receive_json(): ?array;

    /** Message binaire suivant. */
    public function receive_bytes(): ?string;
}
