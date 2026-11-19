# Cookies

## Ce que vous allez apprendre

Lire un cookie avec `#[Cookie]`, poser un cookie de réponse avec ses options, et
utiliser la session PHP via le middleware fourni.

## Lire un cookie

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Cookie;

$app = new EorbahAPI();

$app->get('/profil', function (#[Cookie] ?string $session = null) {
    return ['session' => $session];
});

$app->run();
```

```console
$ curl "http://127.0.0.1:8000/profil" --cookie "session=abc123"
{"session":"abc123"}
```

Un cookie requis (sans valeur par défaut ni type nullable) absent produit une
**422** (`loc: ["cookie", "session"]`).

## Poser un cookie de réponse

Un handler peut recevoir l'objet `Response` et y ajouter un cookie :

```php
<?php

use Eorbahapi\Response;

$app->post('/login', function (Response $response) {
    $response->cookie('session', 'abc123', [
        'expires' => time() + 3600,
        'path' => '/',
        'secure' => false,      // true en production HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    return ['connecté' => true];
});
```

Options acceptées par `Response::cookie()` :

| Option | Défaut | Rôle |
| --- | --- | --- |
| `expires` | `0` (session) | expiration (timestamp) |
| `path` | `/` | portée du cookie |
| `domain` | `''` | domaine |
| `secure` | `true` | HTTPS uniquement |
| `httponly` | `true` | inaccessible en JavaScript |
| `samesite` | `Strict` | `Strict`, `Lax` ou `None` |

**Supprimer un cookie** :

```php
<?php

$app->post('/logout', function (Response $response) {
    $response->clearCookie('session');

    return ['déconnecté' => true];
});
```

## Session PHP

Le middleware `SessionMiddleware` démarre la session et expose les helpers de
`Request` :

```php
<?php

use Eorbahapi\Middlewares\SessionMiddleware;

$app->add_middleware(SessionMiddleware::class);

$app->get('/panier', function (Request $request) {
    $request->setSessionValue('vu', true);

    return ['panier' => $request->session('panier', [])];
});
```

Voir [Middlewares](middlewares.md) pour le cycle de session et ses options.

!!! warning "Cookie `secure` par défaut"
    `secure` vaut `true` par défaut : en développement HTTP, passez
    `'secure' => false` ou le navigateur ignorera le cookie.

## Récapitulatif

- `#[Cookie]` lit un cookie, avec validation et valeur par défaut ;
- `Response::cookie()` pose un cookie avec ses options (durée, `secure`,
  `SameSite`…) ;
- `Response::clearCookie()` le supprime ;
- `SessionMiddleware` gère la session PHP et `Request::session()`.

[Suivant : Réponses](responses.md){ .md-button }
