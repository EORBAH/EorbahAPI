<?php

namespace Eorbahapi\Params;

use Attribute;

/**
 * Paramètre protégé par un schéma de sécurité.
 *
 * Équivalent de `Security()` de FastAPI : la dépendance désignée est exécutée
 * et sa valeur de retour est injectée dans le paramètre.
 *
 *     use Eorbahapi\Security\HTTP\HTTPBearer;
 *
 *     $app->get('/me', function (#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $token) {
 *         ...
 *     });
 *
 * La dépendance peut être :
 *  - une classe instanciable : si l'instance est appelable (`__invoke`), elle est
 *    invoquée et sa valeur est retournée (cas des schémas `HTTPBearer`,
 *    `APIKeyHeader`, `OAuth2PasswordBearer`...) ; si elle implémente
 *    `DependencyInterface`, `resolve($request, $response)` est utilisé ;
 *  - un callable (`[Classe::class, 'methode']` ou nom de fonction).
 *
 * Si `dependency` est omise, la classe déclarée dans le type du paramètre est
 * utilisée. `scopes` est conservé comme métadonnée OAuth2 (aucun contrôle
 * automatique : la logique de portée appartient au schéma de sécurité).
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Security extends Param
{
    /**
     * @param string|array|null $dependency Schéma de sécurité ou callable à exécuter.
     * @param array             $scopes     Portées OAuth2 demandées (métadonnée).
     */
    public function __construct(
        public string|array|null $dependency = null,
        public array $scopes = [],
        ?string $name = null,
    ) {
        parent::__construct(name: $name);
    }

    public function location(): ParamTypes
    {
        return ParamTypes::SECURITY;
    }
}
