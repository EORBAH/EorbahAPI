# Cookies

## Ce que vous allez apprendre

Lire un cookie avec `#[Cookie]`, distinguer un cookie obligatoire d'un cookie
optionnel (valeur par défaut PHP), valider la valeur avec les contraintes Svinx,
comprendre la réponse `422` — et écrire un cookie côté réponse.

## Le code

`docs_src/CookiesExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Cookie;

/**
 * Cookies.
 */
final class CookiesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/session', function (#[Cookie] string $session_id) {
            return ['session_id' => $session_id];
        });

        // Cookie optionnel, avec contrainte de format.
        $app->get('/theme', function (
            #[Cookie(choices: ['light', 'dark'])] string $theme = 'light'
        ) {
            return ['theme' => $theme];
        });

        return $app;
    }
}
```

`Cookie` vit dans le namespace `Eorbahapi\Params` :
`use Eorbahapi\Params\Cookie;`. La lecture se fait dans `$_COOKIE` — c'est ce que
fait `Eorbahapi\Request::cookie()`, que le résolveur de paramètres interroge.
Rien d'autre n'est à câbler, et la signature du handler suffit à décrire le
contrat.

| Écriture | Comportement |
| --- | --- |
| `#[Cookie] string $session_id` | cookie `session_id` **obligatoire**, `422` s'il manque |
| `#[Cookie] string $theme = 'light'` | cookie **optionnel**, `light` par défaut |
| `#[Cookie(choices: ['light', 'dark'])]` | valeur restreinte, `422` sinon |
| `#[Cookie('sid')] string $session_id` | nom explicite `sid` dans les cookies |

## Ce qui se passe

Les scénarios de l'exemple décrivent ce comportement :

```php
    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/session', [
                'cookies' => ['session_id' => 'abc123'],
            ], json: ['session_id' => 'abc123']),

            self::scenario('GET', '/session', [], status: 422, contains: 'missing'),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'dark'],
            ], json: ['theme' => 'dark']),

            self::scenario('GET', '/theme', json: ['theme' => 'light']),

            self::scenario('GET', '/theme', [
                'cookies' => ['theme' => 'rose'],
            ], status: 422, contains: 'value_error'),
        ];
    }
```

| Requête | Statut | Corps / en-têtes |
| --- | --- | --- |
| `GET /session` avec `session_id=abc123` | 200 | `{"session_id":"abc123"}` |
| `GET /session` sans cookie | 422 | `missing` : le paramètre `cookie 'session_id'` est requis |
| `GET /theme` avec `theme=dark` | 200 | `{"theme":"dark"}` |
| `GET /theme` sans cookie | 200 | `{"theme":"light"}` (valeur par défaut) |
| `GET /theme` avec `theme=rose` | 422 | `value_error` : `rose` hors de `choices` |

Le corps d'une `422` reste un JSON
`{"error": true, "status": 422, "message": ..., "details": [...]}` : chaque
entrée de `details` indique `loc` (`["cookie", "theme"]`), `type` (`missing` ou
`value_error`) et `msg`. Les contraintes disponibles sont celles de
`Svinx\Validator` (`min_length`, `max_length`, `pattern`, `choices`, `ge`, `le`…),
en arguments nommés de l'attribut.

Pour **écrire** un cookie, c'est la réponse qui s'en charge. `Response::cookie()`
accepte un nom, une valeur et un tableau d'options ; les valeurs par défaut sont
sûres : `path => '/'`, `secure => true`, `httponly => true`,
`samesite => 'Strict'`, `expires => 0` (cookie de session). L'appel enregistre
aussi la valeur dans `$_COOKIE`, si bien que la requête suivante du même
processus la relit. Pour supprimer un cookie, `Response::clearCookie($name)` :
il réexpédie le cookie avec une date d'expiration passée
(`time() - 3600`) et le retire de `$_COOKIE`. En pratique, dans un handler :

`$response->cookie('theme', 'dark', ['path' => '/']);`

:::callout{type="success" title="Défaut PHP plutôt que défaut d'attribut"}
`#[Cookie] string $theme = 'light'` laisse le défaut dans la signature PHP :
c'est la forme la plus lisible, et elle fonctionne même sans cookie. Les
deux autres écritures (`default: 'light'` dans l'attribut, ou un type
nullable `?string $theme = null`) sont équivalentes.
:::

:::callout{type="warning" title="Un cookie de session n'est pas chiffré"}
Un cookie circule en clair chez le client : ne stockez jamais un mot de
passe ou un rôle d'autorisation directement dedans. Gardez la valeur opaque
(identifiant de session, jeton signé) et vérifiez-la côté serveur.
:::

## Récapitulatif

- `#[Cookie]` lit `$_COOKIE` via `Eorbahapi\Request::cookie()` ;
- sans défaut PHP, le cookie est obligatoire et son absence donne une `422` `missing` ;
- `= 'light'` (ou `default:`) rend le cookie optionnel ;
- `choices`, `pattern`, `min_length`… valident la valeur et produisent une `422` `value_error` ;
- pour écrire, utilisez `Response::cookie()` et `Response::clearCookie()`.

[Suivant : Réponses](#/fr/responses)
