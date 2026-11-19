# Gestion des erreurs

## Ce que vous allez apprendre

Comprendre les réponses d'erreur par défaut, lever une `HTTPException`, intercepter
les erreurs de validation et brancher vos propres gestionnaires.

## Erreurs HTTP

`Eorbahapi\Exceptions\HTTPException` produit une réponse JSON avec le statut
demandé :

```php
<?php

use Eorbahapi\Exceptions\HTTPException;

$app->get('/items/{id}', function (int $id) {
    if ($id !== 42) {
        throw new HTTPException(404, "L'item $id n'existe pas.");
    }

    return ['id' => 42];
});
```

```php
<?php

use Eorbahapi\Responses\JSONResponse;

$app->get('/admin', function () {
    throw new HTTPException(403, 'Accès refusé.', ['X-Reason' => 'role']);
});

// Réponse : 403
// {"error":true,"status":403,"message":"Accès refusé."}
```

## Erreurs de validation

Les erreurs de paramètres deviennent des `RequestValidationError` (422) :

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [
    {"loc": ["query", "limit"], "type": "value_error", "msg": "Le paramètre query 'limit' doit être >= 1"}
  ]
}
```

`RequestValidationError` étend `ValidationException` : les deux passent par le
même gestionnaire. `loc` indique l'emplacement et le nom du paramètre fautif.

## Erreurs de programmation

| Exception | Rendu par défaut |
| --- | --- |
| `Exceptions\DependencyScopeError` | 500 (dépendance impossible à résoudre) |
| `Exceptions\EorbahAPIError` | 500 (erreur du framework) |
| toute autre `\Throwable` | 500 `{"error":true,"status":500,"message":"Internal Server Error"}` |

En mode `debug`, le message d'origine est ajouté (`"debug": "…"`). Activez-le
uniquement en développement :

```php
$app = new EorbahAPI(debug: true);
```

## Gestionnaires personnalisés

`setExceptionHandler()` associe une exception à un callable
`(Throwable $e, Request $request, Response $response)` ; son retour est normalisé
comme une réponse :

```php
<?php

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

$app->setExceptionHandler(HTTPException::class, function (
    HTTPException $e,
    Request $request,
    Response $response,
) {
    return new JSONResponse([
        'detail' => $e->getMessage(),
        'chemin' => $request->path(),
    ], $e->getStatusCode());
});
```

Le gestionnaire le plus spécifique gagne ; `Throwable::class` sert de filet de
sécurité global.

## Route 404 personnalisée

Pour les requêtes qui ne correspondent à aucune route :

```php
<?php

use Eorbahapi\Responses\JSONResponse;

$app->default_route(404, fn () => new JSONResponse(['detail' => 'Route introuvable'], 404));
```

Sans handler, `default_route(405)` se contente de fixer le statut.

## Rendre la même erreur en HTML

Un gestionnaire peut produire autre chose que du JSON :

```php
<?php

use Eorbahapi\Responses\HTMLResponse;

$app->setExceptionHandler(\Throwable::class, function (\Throwable $e) {
    return new HTMLResponse('<h1>Erreur serveur</h1>', 500);
});
```

!!! warning "Erreurs et OpenAPI"
    Les opérations documentées déclarent une réponse 422 générée
    automatiquement. Les erreurs métier (404, 403…) peuvent être ajoutées au
    schéma avec les options d'opération de [Documentation OpenAPI](openapi.md).

## Récapitulatif

- `throw new HTTPException($status, $message, $headers)` produit une réponse
  JSON dédiée ;
- les erreurs de validation sont des 422 avec `details` localisées ;
- les erreurs inattendues deviennent des 500, détaillées en mode `debug` ;
- `setExceptionHandler()` personnalise le rendu par type d'exception ;
- `default_route()` gère les chemins inconnus.

[Suivant : JSON-RPC](rpc.md){ .md-button }
