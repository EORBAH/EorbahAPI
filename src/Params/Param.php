<?php

namespace Eorbahapi\Params;

use Attribute;
use Svinx\Validator\FieldInfo;

/**
 * Base commune des paramètres de route.
 *
 * Un `Param` est un `FieldInfo` de Svinx\Validator : il porte donc les mêmes
 * contraintes (`gt`, `gte`, `lt`, `lte`, `multiple_of`, `min_length`,
 * `max_length`, `pattern`, `choices`, `default`, `default_factory`...) et il est
 * directement utilisable par `Type::withConstraints()` pour valider la valeur.
 *
 * Il ajoute deux choses propres à HTTP :
 *  - le `name` du paramètre sur le fil (nom du champ, en-tête, cookie ou
 *    placeholder de chemin) ; à défaut, le nom du paramètre PHP est utilisé ;
 *  - la `location()`, c'est-à-dire l'endroit où lire la valeur.
 *
 *     $app->get('/users', function (#[Query(gte: 1, lte: 100)] int $limit = 10) {
 *         // ?limit=42  => 42 (int)
 *     });
 *
 *     $app->post('/users', function (#[Body] CreateUser $payload) {
 *         // tout le corps JSON est validé par le modèle Svinx
 *     });
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
abstract class Param extends FieldInfo
{
    /**
     * Nom du paramètre tel qu'il apparaît dans la requête HTTP.
     * Null : le nom du paramètre PHP fait foi.
     */
    public readonly ?string $name;

    public function __construct(
        ?string $name = null,
        mixed $default = null,
        $default_factory = null,
        ?string $alias = null,
        ?string $title = null,
        ?string $description = null,
        float|int|null $gt = null,
        float|int|null $ge = null,
        float|int|null $lt = null,
        float|int|null $le = null,
        float|int|null $gte = null, // alias recommender
        float|int|null $lte = null, // alias recommender
        float|int|null $multiple_of = null,
        ?int $min_length = null,
        ?int $max_length = null,
        ?string $pattern = null,
        ?array $choices = null,
        array $extra = [],
    ) {
        parent::__construct(
            default: $default,
            default_factory: $default_factory,
            alias: $alias,
            title: $title,
            description: $description,
            gt: $gt,
            ge: $gte ?? $ge,
            lt: $lt,
            le: $lte ?? $le,
            multiple_of: $multiple_of,
            min_length: $min_length,
            max_length: $max_length,
            pattern: $pattern,
            choices: $choices,
            extra: $extra,
        );

        // `alias` (Svinx) et `name` (HTTP) désignent la même chose : le nom
        // exposé. L'un ou l'autre peut être fourni.
        $this->name = $name ?? $alias;
    }

    /**
     * Endroit de la requête où lire la valeur du paramètre.
     */
    abstract public function location(): ParamTypes;

    /**
     * Nom du paramètre sur le fil, avec repli sur le nom du paramètre PHP.
     */
    public function nameFor(string $parameter): string
    {
        return $this->name ?? $parameter;
    }
}
