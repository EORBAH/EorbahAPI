# En-têtes

## Ce que vous allez apprendre

Lire un en-tête HTTP avec `#[Header]`, gérer la casse et la convention de nommage,
et utiliser les en-têtes pour l'authentification ou le contenu.

## Déclarer un en-tête

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Header;

$app = new EorbahAPI();

$app->get('/me', function (#[Header('X-Api-Key', min_length: 8)] string $apiKey) {
    return ['key' => $apiKey];
});

$app->run();
```

```console
$ curl "http://127.0.0.1:8000/me" -H "X-Api-Key: secret-123"
{"key":"secret-123"}
```

Un en-tête requis absent produit une **422** (`loc: ["header", "X-Api-Key"]`).

## Casse et nommage

La recherche est insensible à la casse et convertit les `_` PHP en `-` HTTP :

```php
<?php

$app->get('/client', function (
    #[Header] ?string $user_agent = null,
    #[Header] string $accept = 'application/json',
) {
    return ['ua' => $user_agent, 'accept' => $accept];
});
```

- `$user_agent` lit `User-Agent` (ou `user_agent` si le client envoie ce nom) ;
- `$accept` a une valeur par défaut, donc l'en-tête est optionnel ;
- le nom exposé peut être fixé : `#[Header('X-Request-Id')] string $requestId`.

## Contraintes

Les contraintes de Svinx s'appliquent comme pour les autres paramètres :

```php
<?php

$app->get('/secure', function (
    #[Header('Authorization', min_length: 7)] string $authorization,
) {
    return ['ok' => true];
});
```

Voir [Validations](validations.md).

## En-têtes de réponse

Pour poser un en-tête dans la réponse, utilisez `Response` :

```php
<?php

use Eorbahapi\Request;
use Eorbahapi\Response;

$app->get('/trace', function (Request $request, Response $response) {
    $response->headers['X-Trace-Id'] = 'abc-123';

    return ['ok' => true];
});
```

Un handler peut aussi retourner une `Response` avec ses en-têtes (voir
[Réponses](responses.md)).

!!! tip "Authentification"
    `#[Header]` est la brique de base. Pour un schéma complet (challenge 401,
    validation du jeton, documentation OpenAPI), utilisez
    `#[Security(HTTPBearer::class)]` — voir [Sécurité](security.md).

## Récapitulatif

- `#[Header]` lit un en-tête de requête, insensible à la casse ;
- `_` devient `-` (convention FastAPI) ;
- une valeur par défaut rend l'en-tête optionnel ;
- les contraintes Svinx s'appliquent (longueur, motif, choix…) ;
- les en-têtes de réponse se posent sur `Response`.

[Suivant : Cookies](cookies.md){ .md-button }
