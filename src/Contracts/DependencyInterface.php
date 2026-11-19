<?php

namespace Eorbahapi\Contracts;

use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Contrat des dépendances résolues par `#[Depends]` / `#[Security]`.
 *
 * La signature utilise les objets concrets du pipeline actuel : c'est ce qui
 * permet à `Eorbahapi\Dependency\DependencyInterface` (et donc à toutes les
 * dépendances existantes) d'en hériter sans modification.
 */
interface DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed;
}
