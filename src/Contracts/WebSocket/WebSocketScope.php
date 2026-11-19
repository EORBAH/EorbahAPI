<?php

namespace Eorbahapi\Contracts\WebSocket;

/**
 * Contexte d'une connexion WebSocket (équivalent du `scope` d'ASGI).
 */
interface WebSocketScope
{
    /** Type de connexion (`websocket`). */
    public function type(): string;

    /** Chemin demandé, query exclue. */
    public function path(): string;

    /** @return array<string, string> En-têtes de la poignée de main. */
    public function headers(): array;

    /** @return array<string, mixed> Paramètres de la chaîne de requête. */
    public function query(): array;

    /** @return array<string, string> Paramètres extraits du chemin. */
    public function params(): array;

    /** Adresse du client (`host:port`), si connue. */
    public function client(): ?string;

    /** Adresse du serveur (`host:port`), si connue. */
    public function server(): ?string;
}
