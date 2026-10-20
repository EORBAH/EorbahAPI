# Gestion des erreurs

## Ce que vous allez apprendre

Lever une erreur HTTP contrôlée, produire une réponse 422 de validation,
enregistrer un gestionnaire pour vos propres exceptions, comprendre la réponse
500 — et personnaliser le 404.

## Le code

`docs_src/ErrorsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;
use Eorbahapi\Responses\JSONResponse;

/**
 * Gestion des erreurs : exceptions HTTP, validation, gestionnaire personnalisé.
 */
final class ErrorsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // HTTPException : statut et message contrôlés.
        $app->get('/forbidden', function () {
            throw new HTTPException(403, 'Accès interdit');
        });

        // ValidationException : réponse 422 avec le détail des erreurs.
        $app->post('/validate', function () {
            throw new ValidationException(
                [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
                'Request validation error'
            );
        });

        // Gestionnaire personnalisé pour un type d'exception.
        $app->setExceptionHandler(\DomainException::class, fn (\Throwable $error) => new JSONResponse(
            ['domain' => $error->getMessage()],
            400
        ));
        $app->get('/domain', function () {
            throw new \DomainException('règle métier violée');
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/forbidden', status: 403, json: [
                'error' => true,
                'status' => 403,
                'message' => 'Accès interdit',
            ]),

            self::scenario('POST', '/validate', ['json' => []], status: 422, json: [
                'error' => true,
                'status' => 422,
                'message' => 'Request validation error',
                'details' => [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ name est requis.']],
            ]),

            self::scenario('GET', '/domain', status: 400, json: ['domain' => 'règle métier violée']),
        ];
    }
}
```

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /forbidden` | 403 | `{"error":true,"status":403,"message":"Accès interdit"}` |
| `POST /validate` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["body","name"],"type":"missing","msg":"Le champ name est requis."}]}` |
| `GET /domain` | 400 | `{"domain":"règle métier violée"}` |

### Les exceptions du framework

`Eorbahapi\Exceptions\HTTPException($code, $message, $headers)` porte un statut,
un message et des en-têtes optionnels, exposés par `getStatusCode()` et
`getHeaders()`. `Eorbahapi\Exceptions\ValidationException($errors, $message,
$headers)` transporte une liste d'erreurs au format de FastAPI (`loc`, `type`,
`msg`) et produit toujours un 422 ; `RequestValidationError` en est la variante
levée automatiquement quand un paramètre de requête ne passe pas la validation.

Vous pouvez lever ces exceptions depuis n'importe quel handler : le framework
les convertit en `Response` avant que quoi que ce soit ne soit envoyé.

### Les gestionnaires par défaut

`Eorbahapi\ExceptionHandlers` enregistre trois gestionnaires au démarrage de
l'application. `handleExceptionAsResponse()` cherche d'abord un gestionnaire
pour la classe exacte de l'exception, puis le premier gestionnaire dont le type
accepte l'exception (`instanceof`) :

| Exception | Réponse |
| --- | --- |
| `HTTPException` | JSON `{error, status, message}` avec le statut de l'exception et ses en-têtes |
| `ValidationException` | JSON `{error, status, message, details}` en 422 |
| `\Throwable` | JSON `{error, status, message, debug}` en 500 |

### Votre propre gestionnaire

`$app->setExceptionHandler(Classe::class, callable)` associe une exception à une
fonction qui reçoit `($exception, $request, $response)` et retourne une
`Response` — ou tout ce qu'un handler peut retourner. C'est ainsi que
`\DomainException` devient ici un 400 au corps métier. Enregistrez le
gestionnaire le plus spécifique : pour une même famille d'exceptions, c'est le
premier enregistré qui gagne.

!!! warning "La réponse 500 reste générique"
    Une exception non gérée produit `{"error":true,"status":500,"message":"Internal
    Server Error","debug":null}`. Le champ `debug` ne contient le message réel
    qu'avec `new EorbahAPI(debug: true)` (ou `APP_DEBUG=1`, `APP_ENV=dev`). En
    production, laissez `debug` à `false` : le détail interne ne doit pas sortir.

!!! tip "Personnaliser le 404"
    `$app->default_route(404, $handler)` remplace la réponse `Not Found` : le
    handler reçoit ses paramètres via le resolver (une `Request` est injectable)
    et la réponse prend le statut configuré. Appelé sans handler,
    `$app->default_route(404)` renvoie une réponse vide avec ce statut.

## Récapitulatif

- `new HTTPException(403, 'Accès interdit')` produit un JSON `{error, status, message}` ;
- `ValidationException($errors, $message)` produit un 422 enrichi de `details` ;
- les trois gestionnaires par défaut de `Eorbahapi\ExceptionHandlers` couvrent HTTP, validation et `\Throwable` ;
- `$app->setExceptionHandler(Classe::class, fn ($e) => new JSONResponse(...))` ajoute le vôtre ;
- une exception non gérée donne un 500 générique, détaillé seulement en mode `debug` ;
- `$app->default_route(404, $handler)` personnalise la route inconnue.

[Suivant : JSON-RPC](rpc.md){ .md-button }
