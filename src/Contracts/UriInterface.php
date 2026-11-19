<?php

namespace Eorbahapi\Contracts;

/**
 * Contrat minimal d'une URI.
 */
interface UriInterface
{
    public function __toString(): string;

    public function getScheme(): ?string;

    public function getHost(): ?string;

    public function getPort(): ?int;

    public function getPath(): string;

    public function getQuery(): string;
}
