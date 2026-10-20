<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre lu depuis le corps de la requête.
 *
 * Deux usages, selon le type déclaré du paramètre :
 *
 *  - le type est un modèle Svinx (`BaseModel`) : tout le corps (JSON décodé ou
 *    `$_POST`) est confié au modèle, qui applique ses `#[Field]`, ses
 *    `#[FieldValidator]` et sa `Config` ;
 *
 *        #[Body] CreateUser $payload
 *
 *  - le type est scalaire : le champ portant le nom du paramètre est extrait du
 *    corps puis validé ; à défaut de champ, le corps entier est utilisé pour les
 *    types `array` / `mixed`.
 *
 *        #[Body(name: 'user_name', min_length: 2)] string $name
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body extends Param
{
    public function location(): ParamTypes
    {
        return ParamTypes::BODY;
    }
}
