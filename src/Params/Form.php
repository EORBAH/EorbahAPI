<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre lu depuis un formulaire `application/x-www-form-urlencoded`
 * ou `multipart/form-data` (`$_POST`).
 *
 *     #[Form(ge: 18, le: 130)] int $age
 *     #[Form(name: 'e-mail')] EmailStr $email
 *
 * Les valeurs arrivent en chaînes : la coercition laxiste de Svinx\Validator
 * s'applique avant le contrôle du type et des contraintes.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Form extends Body
{
    public function location(): ParamTypes
    {
        return ParamTypes::FORM;
    }
}
