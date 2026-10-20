# Paramètres de chemin

## Ce que vous allez apprendre

Déclarer un placeholder `{nom}` dans une route, le recevoir en `int` grâce à
l'attribut `#[Path]`, lui appliquer des contraintes de validation, et lire enfin
une erreur `422` pour comprendre où le paramètre fautif est signalé.

## Le code

`docs_src/PathParamsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;

final class PathParamsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // `{id}` est converti en int et doit être >= 1.
        $app->get('/users/{id}', function (#[Path(ge: 1)] int $id) {
            return ['id' => $id, 'type' => get_debug_type($id)];
        });

        // Placeholder nommé différemment du paramètre PHP.
        $app->get('/articles/{slug}', function (#[Path('slug', min_length: 3)] string $name) {
            return ['slug' => $name];
        });

        // Capture du reste du chemin.
        $app->get('/files/{chemin:path}', function (#[Path] string $chemin) {
            return ['path' => $chemin];
        });

        return $app;
    }
}
```

## Ce qui se passe

L'attribut s'appelle `Eorbahapi\Params\Path`. Il se place **sur le paramètre** de
la fonction, juste devant la déclaration de type, et il indique deux choses au
framework : d'où vient la valeur (les paramètres de route extraits par le
routeur) et comment la valider.

Le nom du paramètre PHP et le nom du placeholder sont liés par défaut : `int $id`
lit `{id}`. Pour utiliser un autre nom côté URL, passez-le en premier argument de
l'attribut : `#[Path('slug', min_length: 3)] string $name` lit `{slug}` et le
range dans `$name`.

La **coercition de type** est automatique. La valeur brute d'une URL est toujours
une chaîne ; `int $id` la convertit (`"7"` devient `7`), ce que confirme le champ
`type` du premier scénario, égal à `int`. Si la conversion échoue (`/users/abc`),
la réponse est un `422`.

Les contraintes de validation viennent de `Svinx\Validator\FieldInfo` :
`Eorbahapi\Params\Path` en hérite, donc tout ce que vous connaissez déjà de Svinx
fonctionne ici — `ge` (>=), `le` (<=), `gt`, `lt`, `multiple_of`, `min_length`,
`max_length`, `pattern`, `choices`… Le chapitre [Validations](validations.md)
dresse la liste complète.

`{chemin:path}` est un cas particulier du routeur : le suffixe `:path` change le
motif de capture, qui passe de « un segment » à « tout le reste du chemin ». Un
seul placeholder suffit alors pour `/files/a/b/c.txt`.

### Les scénarios de l'exemple

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/users/7', json: ['id' => 7, 'type' => 'int']),
        self::scenario('GET', '/users/0', status: 422, contains: 'value_error'),
        self::scenario('GET', '/articles/php-8', json: ['slug' => 'php-8']),
        self::scenario('GET', '/articles/ab', status: 422, contains: 'value_error'),
        self::scenario('GET', '/files/a/b/c.txt', json: ['path' => 'a/b/c.txt']),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /users/7` | 200 | `{"id":7,"type":"int"}` |
| `GET /users/0` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["path","id"],"type":"value_error","msg":"Le paramètre path 'id' doit être >= 1"}]}` |
| `GET /articles/php-8` | 200 | `{"slug":"php-8"}` |
| `GET /articles/ab` | 422 | `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["path","slug"],"type":"value_error","msg":"Le paramètre path 'slug' doit avoir au moins 3 caractères"}]}` |
| `GET /files/a/b/c.txt` | 200 | `{"path":"a/b/c.txt"}` |

!!! note "Comment lire une erreur 422"
    Le corps est toujours structuré de la même manière :
    `details[0].loc` vaut `["path", "id"]` — l'emplacement (`path`) puis le nom
    du paramètre tel qu'il apparaît dans l'URL (`id`) — et `details[0].msg`
    contient la phrase de Svinx, en français. Le handler n'est pas exécuté : la
    validation a lieu **avant** l'appel.

!!! warning "Le routeur normalise le chemin avant de chercher"
    Puisque le slash final est retiré, `GET /files/` devient `/files` et ne
    correspond plus à `/files/{chemin:path}` : vous obtenez `404`, pas un chemin
    vide. Et comme le motif de `{chemin:path}` est `.*`, il capture aussi les
    slashs internes — d'où l'absence de `/files/` explicite dans la route.

## Récapitulatif

- `#[Path]` (namespace `Eorbahapi\Params\Path`) lit un placeholder `{nom}` ;
- le nom du paramètre PHP sert de nom de placeholder par défaut ;
- `#[Path('slug', ...)]` associe un placeholder à un paramètre PHP de nom différent ;
- le type déclaré provoque la coercition (`int $id` reçoit bien `7`) ;
- les contraintes sont celles de `Svinx\Validator\FieldInfo` (`ge`, `min_length`…) ;
- `{chemin:path}` capture le reste du chemin, slashs compris ;
- une contrainte violée donne `422` avec `details[0].loc = ['path', 'id']`.

[Suivant : Paramètres de requête](query-params.md){ .md-button }
