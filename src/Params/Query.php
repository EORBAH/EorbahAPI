<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre lu depuis la chaîne de requête (`$_GET`).
 *
 *     #[Query(ge: 1, le: 100)] int $limit = 10
 *     #[Query('search', min_length: 2)] ?string $q = null
 *     #[Query(max_length: 5)] array $tags
 *
 * La valeur brute est toujours une chaîne : elle est coercée puis validée par
 * Svinx\Validator selon le type déclaré du paramètre.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Query extends Param
{
    public function location(): ParamTypes
    {
        return ParamTypes::QUERY;
    }
}
