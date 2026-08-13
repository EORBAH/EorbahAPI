# SessionMiddleware

Gestion des sessions côté serveur avec support des cookies et du stockage persistant.

## Description

Le middleware `SessionMiddleware` initialise et gère les sessions PHP, permettant de stocker des données utilisateur persistantes entre les requêtes.

## Configuration

```php
use Eorbahapi\Middlewares\SessionMiddleware;

$app->addMiddleware(SessionMiddleware::class);
```

Avec options :

```php
$app->addMiddleware(
    SessionMiddleware::class,
    [
        'session_name'    => 'EORBAHAPI',
        'cookie_lifetime' => 3600,
        'cookie_path'     => '/',
        'cookie_domain'   => null,
        'cookie_secure'   => false,
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax'
    ]
);
```

## Paramètres

| Paramètre | Type | Description |
|-----------|------|-------------|
| `session_name` | string | Nom du cookie de session |
| `cookie_lifetime` | int | Durée de vie du cookie en secondes |
| `cookie_path` | string | Chemin du cookie |
| `cookie_domain` | ?string | Domaine du cookie (null = domaine courant) |
| `cookie_secure` | bool | Transmettre uniquement via HTTPS |
| `cookie_httponly` | bool | Empêcher l'accès JavaScript au cookie |
| `cookie_samesite` | string | Protection CSRF : `Lax`, `Strict`, ou `None` |

## Utilisation

Une fois le middleware activé, les données de session sont accessibles via `$_SESSION` :

```php
$app->get('/login', function () {
    $_SESSION['user_id'] = 123;
    return ['message' => 'Session établie'];
});

$app->get('/profile', function () {
    if (isset($_SESSION['user_id'])) {
        return ['user_id' => $_SESSION['user_id']];
    }
    return ['error' => 'Non autorisé'];
});
```

## Exemple complet

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\SessionMiddleware;

$app = new EorbahAPI('API');

// Configuration des sessions
$app->addMiddleware(
    SessionMiddleware::class,
    [
        'cookie_httponly' => true,
        'cookie_secure'   => true,  // Production only
        'cookie_samesite' => 'Strict'
    ]
);

$app->post('/login', function ($request) {
    // Valider les identifiants
    $_SESSION['user_id'] = 456;
    $_SESSION['logged_in'] = true;
    
    return ['status' => 'logged in'];
});

$app->get('/me', function () {
    return $_SESSION;
});

$app->post('/logout', function () {
    session_destroy();
    return ['status' => 'logged out'];
});

$app->run();
```

## Notes

- Le middleware doit être ajouté au début de la chaîne pour initialiser les sessions avant les autres middlewares
- Les sessions sont stockées dans le système de fichiers par défaut (`/tmp` ou `C:\Windows\Temp`)
- Pour la production, configurez `cookie_secure => true` et `cookie_samesite => 'Strict'`
