<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre lu depuis les en-têtes HTTP.
 *
 * Le nom est cherché sans tenir compte de la casse et, comme en FastAPI, les
 * `_` sont convertis en `-` : `#[Header] string $user_agent` lit `User-Agent`
 * (mais un en-tête envoyé avec des `_` reste accepté).
 *
 *     #[Header('X-Api-Key', min_length: 16)] string $apiKey
 *     #[Header(default: 'json')] string $accept
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Header extends Param
{
    public function location(): ParamTypes
    {
        return ParamTypes::HEADER;
    }
}
