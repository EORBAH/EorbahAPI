# Paramètres de requête

## Ce que vous allez apprendre

Réceptionner les paramètres d'une URL (`?page=2`), choisir entre résolution
automatique par nom et attribut `#[Query]`, gérer valeurs par défaut, paramètre
obligatoire, paramètre nullable et listes (`?tags[]=php&tags[]=api`).

## Le code

`docs_src/QueryParamsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;

final class QueryParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Sans attribut : les paramètres de l'URL sont résolus par leur nom.
        $app->get('/search', function (string $q = 'tout', int $page = 1) {
            return ['q' => $q, 'page' => $page];
        });

        // `skip`/`limit` avec des valeurs par défaut PHP.
        $app->get('/items', function (#[Query] int $skip = 0, #[Query] int $limit = 10) {
            return ['skip' => $skip, 'limit' => $limit];
        });

        // Paramètre obligatoire (aucun défaut).
        $app->get('/required', function (#[Query] string $token) {
            return ['token' => $token];
        });

        // Paramètre optionnel nullable.
        $app->get('/optional', function (#[Query] ?string $name = null) {
            return ['name' => $name];
        });

        // Liste : `?tags[]=php&tags[]=api`.
        $app->get('/tags', function (#[Query] array $tags = []) {
            return ['tags' => $tags];
        });

        return $app;
    }
}
```

## Ce qui se passe

Trois routes sur cinq se passent d'attribut : `?q=php&page=2` remplit
`string $q` et `int $page` parce que le framework résout chaque paramètre **par
son nom** dans `Request::params()` — les paramètres de route, complétés par la
chaîne de requête. C'est la forme la plus courte, pratique pour des filtres.

L'attribut `Eorbahapi\Params\Query` rend le paramètre explicite. Il apporte deux
choses de plus : la valeur peut porter un **défaut** (`default:`) et elle est
validée par `Svinx\Validator` avec les mêmes contraintes que `#[Path]`.

L'ordre de résolution d'une valeur absente est toujours le même :

1. le défaut porté par l'attribut (`#[Query(default: 1)]`) ;
2. le défaut PHP de la fonction (`int $limit = 10`) ;
3. `null` si le type l'autorise (`?string $name = null`) ;
4. sinon une erreur `422` de type `missing`.

C'est exactement ce que montrent `/items` (défauts PHP), `/required` (aucun
défaut, donc obligatoire) et `/optional` (nullable).

Pour les listes, la convention est celle des formulaires HTML :
`?tags[]=php&tags[]=api` remplit `array $tags = []`. Un `?tags=php` unique est
refusé — Svinx attend un tableau, pas une chaîne.

### Les scénarios de l'exemple

```php
<?php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/search?q=php&page=2', json: ['q' => 'php', 'page' => 2]),
        self::scenario('GET', '/search', json: ['q' => 'tout', 'page' => 1]),
        self::scenario('GET', '/items?skip=5&limit=2', json: ['skip' => 5, 'limit' => 2]),
        self::scenario('GET', '/items', json: ['skip' => 0, 'limit' => 10]),
        self::scenario('GET', '/required?token=abc', json: ['token' => 'abc']),
        self::scenario('GET', '/required', status: 422, contains: 'missing'),
        self::scenario('GET', '/optional', json: ['name' => null]),
        self::scenario('GET', '/tags?tags[]=php&tags[]=api', json: ['tags' => ['php', 'api']]),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /search?q=php&page=2` | 200 | `{"q":"php","page":2}` |
| `GET /search` | 200 | `{"q":"tout","page":1}` |
| `GET /items?skip=5&limit=2` | 200 | `{"skip":5,"limit":2}` |
| `GET /items` | 200 | `{"skip":0,"limit":10}` |
| `GET /required?token=abc` | 200 | `{"token":"abc"}` |
| `GET /required` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["query","token"],"type":"missing","msg":"Le paramètre query 'token' est requis."}]}` |
| `GET /optional` | 200 | `{"name":null}` |
| `GET /tags?tags[]=php&tags[]=api` | 200 | `{"tags":["php","api"]}` |

!!! tip "La coercition des chaînes est laxiste, la validation ne l'est pas"
    Une URL ne transporte que du texte : `#[Query] int $limit` reçoit `"2"` et le
    convertit en `2`, `float` accepte `"2.5"`, `bool` accepte `"true"` et `"1"`.
    En revanche, une valeur non convertible (`?limit=abc`) ne devient pas `0` :
    elle produit un `422` avec un message du type
    `int attendu, string donné ('abc')`.

!!! warning "Sans attribut, il n'y a pas de validation"
    Un paramètre nu (`int $page = 1`) est converti par un simple cast PHP : la
    contrainte de type n'est pas vérifiée et `?page=x` donne silencieusement
    `0`. Dès que vous voulez un refus explicite, des bornes ou une valeur par
    défaut documentée, déclarez `#[Query]` — ou passez aux
    [Validations](validations.md).

## Récapitulatif

- un paramètre sans attribut est résolu par son nom dans la requête ;
- `#[Query]` (namespace `Eorbahapi\Params\Query`) rend le paramètre explicite et validé ;
- défaut de l'attribut, puis défaut PHP, puis `null` si le type l'autorise ;
- aucun défaut et pas de `null` autorisé : `422` de type `missing` ;
- un paramètre optionnel nullable s'écrit `#[Query] ?string $name = null` ;
- les listes passent par `?tags[]=a&tags[]=b` ;
- les chaînes sont converties vers `int`, `float`, `bool` en mode laxiste de Svinx.

[Suivant : Validations](validations.md){ .md-button }
