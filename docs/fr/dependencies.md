# Dépendances

## Ce que vous allez apprendre

Déclarer des paramètres qui ne viennent pas de la requête mais d'une dépendance :
`#[Depends]`, le contrat `DependencyInterface`, une fonction ou une méthode
statique comme dépendance, et le conteneur de l'application.

## Le code

`docs_src/DependenciesExample.php` :

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

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /me` | 200 | `{"user":"anonyme"}` |
| `GET /me` avec `X-User: oumar` | 200 | `{"user":"oumar"}` |
| `GET /greeting` | 200 | `{"message":"Bonjour depuis une fonction"}` |
| `GET /hello` | 200 | `{"message":"Bonjour anonyme"}` |
| `GET /auto` | 200 | `{"message":"Bonjour monde"}` |

### Ce que fait `#[Depends]`

`Eorbahapi\Params\Depends` accepte deux arguments : `dependency` (une classe, une
fonction ou un tableau `[Classe::class, 'methodeStatique']`) et `args` (les
arguments passés au constructeur de la dépendance). La valeur de retour est
injectée dans le paramètre : c'est pour cela que `/me` reçoit une chaîne, et non
l'objet `CurrentUser`.

Une classe qui implémente `Eorbahapi\Dependency\DependencyInterface` reçoit la
requête et la réponse courantes via `resolve(Request $request, Response
$response): mixed`. Une classe ordinaire est instanciée — ou reprise du conteneur
si `$app->register()` l'a déjà enregistrée — et l'instance elle-même est injectée.

### L'ordre de résolution d'un paramètre

`DependencyResolver::resolveParameter()` essaie les sources dans cet ordre et
s'arrête à la première qui répond :

1. les attributs de dépendance (`#[Depends]`, `#[Security]`) ;
2. la valeur fournie par le routeur (segment d'URL, paramètre de body) ;
3. `Request` et `Response`, injectés automatiquement ;
4. le service enregistré dans le conteneur (`$app->register(Greeter::class, new Greeter())`) ;
5. l'instanciation automatique de la classe si son constructeur n'a aucun paramètre requis (`/auto`) ;
6. la valeur par défaut du paramètre ;
7. `null` si le type l'autorise ;
8. sinon une `Eorbahapi\Exceptions\DependencyScopeError`.

!!! tip "Une seule famille d'attributs par paramètre"
    `#[Depends]` sert à calculer une valeur ; `#[Query]`, `#[Path]`, `#[Body]`…
    servent à lire une valeur dans la requête. Déclarer deux attributs de source
    sur le même paramètre lève une `DependencyScopeError`.

!!! note "Ordre des sources"
    Un paramètre typé `Greeter` sans attribut passe par l'étape 4 avant l'étape
    5 : c'est le conteneur qui décide si votre application partage une instance
    ou en fabrique une nouvelle à chaque appel.

## Récapitulatif

- `#[Depends(Classe::class)]` exécute une dépendance et injecte sa valeur ;
- une dépendance est une classe (`DependencyInterface` ou constructeur simple), un nom de fonction, ou `[Classe::class, 'methodeStatique']` ;
- `args:` passe les arguments au constructeur de la dépendance ;
- `$app->register(Cle::class, $service)` alimente le conteneur, consulté avant l'instanciation automatique ;
- la résolution va des attributs vers la valeur de route, `Request`/`Response`, le conteneur, l'autowiring, le défaut, `null`, puis `DependencyScopeError`.

[Suivant : Sécurité](security.md){ .md-button }
