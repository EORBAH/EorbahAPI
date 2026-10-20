# Dependencies

## What you will learn

Declare parameters that do not come from the request but from a dependency:
`#[Depends]`, the `DependencyInterface` contract, a function or a static method
as a dependency, and the application container.

## The code

`docs_src/DependenciesExample.php`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\Dependency\DependencyInterface;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Depends;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Dépendance qui lit l'utilisateur courant dans les en-têtes.
 */
final class CurrentUser implements DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed
    {
        return $request->getHeader('X-User') ?? 'anonyme';
    }
}

/**
 * Service applicatif injecté par le conteneur.
 */
final class Greeter
{
    public function greet(string $name): string
    {
        return 'Bonjour ' . $name;
    }
}

/**
 * Fonction utilisée comme dépendance.
 */
function eorbah_api_greeting(): string
{
    return 'Bonjour depuis une fonction';
}

/**
 * Dépendances : `#[Depends]`, `DependencyInterface` et conteneur.
 */
final class DependenciesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Une dépendance `DependencyInterface` reçoit la requête et la réponse.
        $app->get('/me', function (#[Depends(CurrentUser::class)] string $user) {
            return ['user' => $user];
        });

        // Une fonction peut servir de dépendance.
        $app->get('/greeting', function (#[Depends('\Eorbahapi\Examples\eorbah_api_greeting')] string $message) {
            return ['message' => $message];
        });

        // Service enregistré dans le conteneur puis injecté par son type.
        $app->register(Greeter::class, new Greeter());
        $app->get('/hello', function (Greeter $greeter, #[Depends(CurrentUser::class)] string $user) {
            return ['message' => $greeter->greet($user)];
        });

        // Instanciation automatique (constructeur sans paramètre requis).
        $app->get('/auto', fn (Greeter $greeter) => ['message' => $greeter->greet('monde')]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/me', json: ['user' => 'anonyme']),
            self::scenario('GET', '/me', ['headers' => ['X-User' => 'oumar']], json: ['user' => 'oumar']),
            self::scenario('GET', '/greeting', json: ['message' => 'Bonjour depuis une fonction']),
            self::scenario('GET', '/hello', json: ['message' => 'Bonjour anonyme']),
            self::scenario('GET', '/auto', json: ['message' => 'Bonjour monde']),
        ];
    }
}
```

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `GET /me` | 200 | `{"user":"anonyme"}` |
| `GET /me` with `X-User: oumar` | 200 | `{"user":"oumar"}` |
| `GET /greeting` | 200 | `{"message":"Bonjour depuis une fonction"}` |
| `GET /hello` | 200 | `{"message":"Bonjour anonyme"}` |
| `GET /auto` | 200 | `{"message":"Bonjour monde"}` |

### What `#[Depends]` does

`Eorbahapi\Params\Depends` accepts two arguments: `dependency` (a class, a
function or an array `[Classe::class, 'methodeStatique']`) and `args` (the
arguments passed to the dependency's constructor). The return value is injected
into the parameter: that is why `/me` receives a string, and not the
`CurrentUser` object.

A class that implements `Eorbahapi\Dependency\DependencyInterface` receives the
current request and response via `resolve(Request $request, Response
$response): mixed`. An ordinary class is instantiated — or taken from the
container if `$app->register()` has already registered it — and the instance
itself is injected.

### The resolution order of a parameter

`DependencyResolver::resolveParameter()` tries the sources in this order and
stops at the first one that answers:

1. dependency attributes (`#[Depends]`, `#[Security]`);
2. the value provided by the router (URL segment, body parameter);
3. `Request` and `Response`, injected automatically;
4. the service registered in the container (`$app->register(Greeter::class, new Greeter())`);
5. automatic instantiation of the class if its constructor has no required parameter (`/auto`);
6. the parameter's default value;
7. `null` if the type allows it;
8. otherwise an `Eorbahapi\Exceptions\DependencyScopeError`.

!!! tip "One family of attributes per parameter"
    `#[Depends]` is used to compute a value; `#[Query]`, `#[Path]`, `#[Body]`…
    are used to read a value from the request. Declaring two source attributes
    on the same parameter raises a `DependencyScopeError`.

!!! note "Order of sources"
    A parameter typed `Greeter` without an attribute goes through step 4 before
    step 5: it is the container that decides whether your application shares an
    instance or creates a new one on each call.

## Summary

- `#[Depends(Classe::class)]` runs a dependency and injects its value;
- a dependency is a class (`DependencyInterface` or simple constructor), a function name, or `[Classe::class, 'methodeStatique']`;
- `args:` passes the arguments to the dependency's constructor;
- `$app->register(Cle::class, $service)` feeds the container, which is consulted before automatic instantiation;
- resolution goes from attributes to the route value, `Request`/`Response`, the container, autowiring, the default, `null`, then `DependencyScopeError`.

[Next: Security](security.md){ .md-button }
