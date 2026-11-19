# Paramètres de chemin

## Ce que vous allez apprendre

Déclarer un paramètre extrait du chemin, le typer, lui ajouter des contraintes de
validation et comprendre les réponses 422.

## Déclarer un paramètre

Un segment `{nom}` du chemin se déclare avec l'attribut `#[Path]` sur un
paramètre du handler :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;

$app = new EorbahAPI();

$app->get('/items/{id}', function (#[Path] int $id) {
    return ['id' => $id];
});

$app->run();
```

```console
$ curl "http://127.0.0.1:8000/items/42"
{"id":42}
```

Sans attribut, un paramètre est aussi alimenté par le routeur, mais `#[Path]`
déclare explicitement l'intention et permet la validation.

## Typage et coercition

La valeur du chemin est toujours une chaîne. Svinx\Validator la convertit vers le
type déclaré (`int`, `float`, `bool`, `string`), en mode laxiste :

```php
<?php

$app->get('/users/{id}', fn (#[Path] int $id) => get_debug_type($id)); // "int"
$app->get('/flags/{actif}', fn (#[Path] bool $actif) => get_debug_type($actif)); // "bool"
```

Une valeur impossible à convertir produit une **422** :

```console
$ curl -i "http://127.0.0.1:8000/users/abc"
HTTP/1.1 422 Unprocessable Entity
{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["path","id"],"type":"value_error","msg":"Le paramètre path 'id' doit être de type int"}]}
```

## Contraintes

Les attributs portent les contraintes de `FieldInfo` :

```php
<?php

use Eorbahapi\Params\Path;

$app->get('/articles/{id}', function (
    #[Path(gte: 1)] int $id,
) {
    return ['id' => $id];
});
```

| Contrainte | Effet |
| --- | --- |
| `gte` / `gt` | valeur minimale (incluse / exclue) |
| `le` / `lt` | valeur maximale (incluse / exclue) |
| `multiple_of` | multiple attendu |
| `min_length` / `max_length` | longueur (chaîne) |
| `pattern` | expression régulière |

`gte` et `ge` sont équivalents (`lte` / `le` aussi) : les deux orthographes sont
acceptées, la seconde étant conservée pour la compatibilité.

## Plusieurs paramètres

L'ordre des paramètres PHP suit l'ordre de déclaration des attributs :

```php
<?php

$app->get('/users/{userId}/posts/{postId}', function (
    #[Path(gte: 1)] int $userId,
    #[Path(gte: 1)] int $postId,
) {
    return ['user' => $userId, 'post' => $postId];
});
```

```console
$ curl "http://127.0.0.1:8000/users/7/posts/12"
{"user":7,"post":12}
```

## Paramètre qui capture le reste du chemin

Le suffixe `:path` capture les slashs :

```php
<?php

$app->get('/files/{chemin:path}', function (#[Path] string $chemin) {
    return ['chemin' => $chemin];
});
```

```console
$ curl "http://127.0.0.1:8000/files/docs/api/index.html"
{"chemin":"docs/api/index.html"}
```

Le paramètre est alors toujours une chaîne.

!!! note "Paramètres requis"
    Un paramètre de chemin est toujours requis : une URL sans segment ne
    correspond simplement pas à la route (404), elle ne produit pas de 422.

## Récapitulatif

- `#[Path]` déclare un paramètre extrait du chemin ;
- le typage déclaré (`int`, `float`, `bool`…) active la coercition ;
- les contraintes (`gte`, `le`, `min_length`, `pattern`…) produisent des 422 ;
- plusieurs paramètres suivent l'ordre de déclaration ;
- `{chemin:path}` capture le reste du chemin.

[Suivant : Paramètres de requête](query-params.md){ .md-button }
