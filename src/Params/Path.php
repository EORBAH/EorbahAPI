<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre extrait du chemin de la route (placeholder `{nom}`).
 *
 *     $app->get('/users/{id}', function (#[Path(ge: 1)] int $id) { ... });
 *
 * La valeur provient des paramètres de route résolus par le routeur ; à défaut
 * elle est relue dans les paramètres de la requête.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Path extends Param
{
    public function location(): ParamTypes
    {
        return ParamTypes::PATH;
    }
}
