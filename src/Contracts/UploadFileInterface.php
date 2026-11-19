<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat d'un fichier téléversé.
 *
 * `Eorbahapi\Datastructures\UploadFile` fournit déjà `read()`, `write()`,
 * `seek()` et `close()` ; l'adaptateur
 * `Eorbahapi\Adapter\Datastructures\UploadFileAdapter` ajoute les accesseurs
 * typés et déclare ce contrat.
 */
interface UploadFileInterface
{
    public function getFilename(): string;

    public function getContentType(): string;

    /** Lit jusqu'à $length octets. */
    public function read(?int $length = null): string|false;

    /** Écrit des données dans le flux. */
    public function write(string $data): int|false;

    /** Déplace le curseur du flux. */
    public function seek(int $offset, int $whence = SEEK_SET): int;

    /** Ferme le flux. */
    public function close(): bool;
}
