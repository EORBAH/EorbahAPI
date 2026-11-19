# Dépendances

## Ce que vous allez apprendre

Injecter un service, une connexion ou une valeur calculée dans un handler avec
`#[Depends]`, utiliser le conteneur et l'autowiring.

## Dépendance callable

`#[Depends]` exécute un callable et injecte son retour :

```php
<?php

use Eorbahapi\Params\Depends;
use Eorbahapi\Request;

function client_ip(Request $request): string
{
    return $request->getClientIP();
}

$app->get('/ip', function (
    #[Depends('client_ip')] string $ip,
) {
    return ['ip' => $ip];
});
```

Le callable reçoit lui-même les dépendances résolues (`Request`, `Response`,
services…).

## Dépendance classe

Une classe peut être instanciée puis appelée :

```php
<?php

final class UtilisateurCourant
{
    public function __invoke(Request $request): ?string
    {
        return $request->getHeader('X-User');
    }
}

$app->get('/admin', function (
    #[Depends(UtilisateurCourant::class)] ?string $user,
) {
    return ['user' => $user];
});
```

Si la classe implémente `Eorbahapi\Dependency\DependencyInterface` (qui hérite de
`Eorbahapi\Contracts\DependencyInterface`), `resolve(Request, Response)` est
appelé :

```php
<?php

use Eorbahapi\Dependency\DependencyInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;

final class BaseDeDonnees implements DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed
    {
        return new \PDO('sqlite::memory:');
    }
}

$app->get('/db', function (#[Depends(BaseDeDonnees::class)] \PDO $db) {
    return ['driver' => $db->getAttribute(\PDO::ATTR_DRIVER_NAME)];
});
```

## Conteneur

`register()` associe un identifiant à une instance partagée ; l'autowiring par
type la retrouve sans attribut :

```php
<?php

use Eorbahapi\BackgroundTasks;

$tasks = new BackgroundTasks();
$app->register(BackgroundTasks::class, $tasks);

$app->post('/users', function (BackgroundTasks $background) {
    $background->add(static fn () => null);

    return ['created' => true];
});
```

L'autowiring instancie aussi une classe dont le constructeur n'exige aucun
paramètre (`new $classe()`), et met l'instance en cache.

## `Request` et `Response`

Ils sont injectés automatiquement par type :

```php
<?php

$app->get('/info', function (Request $request, Response $response) {
    $response->headers['X-Method'] = $request->method();

    return ['path' => $request->path()];
});
```

## Résolution et erreurs

Pour un paramètre sans attribut, l'ordre est : valeur fournie par le routeur,
`Request`/`Response`, conteneur, instanciation automatique, valeur par défaut,
`null` si autorisé. Si rien ne convient, `DependencyScopeError` (500) est levée :
c'est une erreur de programmation.

!!! tip "Partager un état par requête"
    Pour un service réellement par requête, passez par `#[Depends]` avec un
    callable ou enregistrez une fabrique qui construit à chaque appel.

## Récapitulatif

- `#[Depends]` injecte le retour d'un callable ou d'une classe ;
- les dépendances reçoivent elles-mêmes `Request`, `Response` et les services ;
- `DependencyInterface::resolve()` permet une logique dédiée ;
- `register()` + type-hint partagent une instance ;
- `Request` et `Response` sont injectés sans configuration.

[Suivant : Sécurité](security.md){ .md-button }
