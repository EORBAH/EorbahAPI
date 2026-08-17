# EorbahAPI — Classe `ExceptionHandlers`

La classe `Eorbahapi\ExceptionHandlers` fournit des gestionnaires d'exception prêts à l'emploi pour les erreurs HTTP, les erreurs de validation et les exceptions génériques.

## Gestionnaires inclus

- `httpExceptionHandler(HTTPException $e, Request $request, Response $response)`
- `requestValidationExceptionHandler(ValidationException $e, Request $request, Response $response)`
- `genericExceptionHandler(\Throwable $e, Request $request, Response $response)`

## Méthode principale

### `overrideExceptionHandlers(EorbahAPI $app): void`

Enregistre les gestionnaires sur l'application. Cette méthode est appelée automatiquement dans le constructeur de `EorbahAPI`, ce qui signifie que l'application utilise déjà une gestion d'erreurs structurée dès l'instanciation.

```php
use Eorbahapi\ExceptionHandlers;

$app = new EorbahAPI();
```

## Comportements

### `httpExceptionHandler`

Transforme une `HTTPException` en réponse JSON structurée avec les champs :

- `error`
- `status`
- `message`

Il réutilise également les en-têtes personnalisés fournis par l'exception.

### `requestValidationExceptionHandler`

Retourne une réponse `422` contenant :

- `error: true`
- `status: 422`
- `message: Validation error`
- `details` : tableau des erreurs de validation

### `genericExceptionHandler`

Retourne une réponse `500` pour toutes les autres exceptions.

- en mode debug (`APP_DEBUG=true` ou `APP_ENV=dev`) : le message de l'exception est exposé dans le champ `debug`
- en production : le champ `debug` est `null`

## Personnalisation

Vous pouvez remplacer ou ajouter vos propres gestionnaires d'exception à l'aide de `setExceptionHandler()` de `EorbahAPI` :

```php
$app->setExceptionHandler(HTTPException::class, function ($e, Request $req, Response $res) {
        return JSONResponse(['error' => true, 'message' => 'Je suis une tasse'], 418);
    }
);
```
