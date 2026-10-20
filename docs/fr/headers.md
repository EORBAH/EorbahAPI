# En-têtes

## Ce que vous allez apprendre

Lire un en-tête HTTP depuis un paramètre de handler avec `#[Header]` : la
recherche insensible à la casse, la convention FastAPI qui transforme
`user_agent` en `User-Agent`, le nom explicite, les contraintes de validation
de Svinx et la réponse `422` renvoyée quand un en-tête obligatoire manque.

## Le code

`docs_src/HeadersExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Header;

/**
 * En-têtes HTTP.
 */
final class HeadersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `token` est cherché sous le nom `token` puis `Token` (insensible à la casse).
        $app->get('/token', function (#[Header] string $token) {
            return ['token' => $token];
        });

        // Nom explicite, contraintes Svinx appliquées à la valeur.
        $app->get('/api-key', function (#[Header('X-Api-Key', min_length: 8)] string $key) {
            return ['key' => $key];
        });

        // Convention FastAPI : `user_agent` lit l'en-tête `User-Agent`.
        $app->get('/agent', function (#[Header] string $user_agent) {
            return ['agent' => $user_agent];
        });

        return $app;
    }
}
```

Un en-tête se déclare comme n'importe quel autre paramètre : le **type PHP**
décide de la conversion, l'**attribut** décide de la source. `Header` vit dans le
namespace `Eorbahapi\Params` et s'importe par
`use Eorbahapi\Params\Header;`.

Trois écritures suffisent pour tout couvrir :

| Écriture | En-tête lu |
| --- | --- |
| `#[Header] string $token` | `Token` (ou `token`, la casse est ignorée) |
| `#[Header] string $user_agent` | `User-Agent` (`_` converti en `-`) |
| `#[Header('X-Api-Key')] string $key` | `X-Api-Key` exactement |

## Ce qui se passe

Les scénarios de l'exemple décrivent ce comportement :

```php
    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/token', [
                'headers' => ['Token' => 'abc'],
            ], json: ['token' => 'abc']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'secret-123'],
            ], json: ['key' => 'secret-123']),

            self::scenario('GET', '/api-key', [
                'headers' => ['X-Api-Key' => 'court'],
            ], status: 422, contains: 'value_error'),

            self::scenario('GET', '/agent', [
                'headers' => ['User-Agent' => 'EorbahBot/1.0'],
            ], json: ['agent' => 'EorbahBot/1.0']),

            self::scenario('GET', '/token', [], status: 422, contains: 'missing'),
        ];
    }
```

| Requête | Statut | Corps / en-têtes |
| --- | --- | --- |
| `GET /token` avec `Token: abc` | 200 | `{"token":"abc"}` |
| `GET /api-key` avec `X-Api-Key: secret-123` | 200 | `{"key":"secret-123"}` |
| `GET /api-key` avec `X-Api-Key: court` | 422 | `value_error` : 5 caractères pour `min_length: 8` |
| `GET /agent` avec `User-Agent: EorbahBot/1.0` | 200 | `{"agent":"EorbahBot/1.0"}` |
| `GET /token` sans en-tête | 422 | `missing` : le paramètre `header 'token'` est requis |

Une erreur de validation ne renvoie jamais de page HTML : le corps est un JSON
`{"error": true, "status": 422, "message": ..., "details": [...]}`, où chaque
entrée de `details` porte `loc` (`["header", "token"]`), `type` (`missing` ou
`value_error`) et `msg`. Les contraintes testées ici sont celles de
`Svinx\Validator` : `min_length`, `max_length`, `pattern`, `ge`, `choices`…
elles s'écrivent en argument nommé de l'attribut.

!!! tip "La casse et les tirets sont gérés"
    Les noms d'en-têtes HTTP sont insensibles à la casse. Le framework compare
    donc les noms en minuscules et tente aussi la variante `_` → `-` : un client
    qui envoie `token`, `Token` ou `TOKEN` alimente le même paramètre, et
    `#[Header] string $user_agent` lit bien `User-Agent`.

!!! warning "Un nom explicite fige le nom sur le fil"
    Dès que vous passez un nom à l'attribut (`#[Header('X-Api-Key')]`), le nom
    du paramètre PHP n'est plus utilisé : `$key` lit `X-Api-Key`, pas `key`.
    C'est la seule façon d'être sûr du nom envoyé par un client tiers.

## Récapitulatif

- `#[Header]` se place sur un paramètre de handler, comme `#[Query]` ou `#[Path]` ;
- la recherche est insensible à la casse, et `_` devient `-` (convention FastAPI) ;
- `#[Header('X-Api-Key')]` impose le nom exact de l'en-tête ;
- les contraintes Svinx (`min_length`, `pattern`, `choices`…) valident la valeur ;
- en-tête absent et valeur invalide produisent une `422` avec `missing` / `value_error`.

[Suivant : Cookies](cookies.md){ .md-button }
