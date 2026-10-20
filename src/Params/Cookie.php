<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre lu depuis les cookies de la requête.
 *
 *     #[Cookie('session_id', pattern: '/^[a-f0-9]{32}$/')] string $session
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Cookie extends Param
{
    public function location(): ParamTypes
    {
        return ParamTypes::COOKIE;
    }
}
