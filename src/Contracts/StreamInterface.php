<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat minimal d'un flux (corps de requête, fichier, flux SSE…).
 */
interface StreamInterface
{
    /** Lit jusqu'à $length octets ; retourne false en fin de flux. */
    public function read(int $length = 8192): string|false;

    /** Écrit des données et retourne le nombre d'octets écrits. */
    public function write(string $data): int|false;

    /** Indique si la fin du flux est atteinte. */
    public function eof(): bool;

    /** Ferme le flux. */
    public function close(): void;
}
