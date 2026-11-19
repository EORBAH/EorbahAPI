<?php

namespace Eorbahapi\Contracts\WebSocket;

/**
 * Envoi de messages sur une connexion WebSocket.
 */
interface WebSocketSendMessage
{
    /** Envoie un message texte. */
    public function send(string $message): void;

    /** Envoie une valeur encodée en JSON (texte). */
    public function send_json(mixed $data): void;

    /** Envoie un message binaire. */
    public function send_bytes(string $data): void;

    /** Ferme la connexion avec un code RFC 6455 (1000 par défaut). */
    public function close(int $code = 1000, string $reason = ''): void;
}
