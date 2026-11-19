# Paramètres de requête

## Ce que vous allez apprendre

Lire la query string avec `#[Query]`, gérer les valeurs optionnelles et les
valeurs par défaut, renommer un paramètre et valider les entrées.

## Déclarer un paramètre de requête

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;

$app = new EorbahAPI();

$app->get('/items', function (#[Query] int $page = 1) {
    return ['page' => $page];
});

$app->run();
```

```console
$ curl "http://127.0.0.1:8000/items?page=3"
{"page":3}
```

Sans `?page=`, la valeur par défaut `1` est utilisée.

## Paramètres optionnels

Un paramètre typé nullable ou avec valeur par défaut est optionnel :

```php
<?php

$app->get('/search', function (
    #[Query(min_length: 2)] ?string $q = null,
    #[Query(le: 100)] int $limit = 10,
) {
    return ['q' => $q, 'limit' => $limit];
});
```

```console
$ curl "http://127.0.0.1:8000/search"
{"q":null,"limit":10}
$ curl "http://127.0.0.1:8000/search?q=clavier&limit=5"
{"q":"clavier","limit":5}
```

!!! warning "Ordre des paramètres PHP"
    Placez les paramètres requis avant les optionnels. PHP 8.3+ déprécie un
    paramètre optionnel déclaré avant un paramètre requis, et la réflexion perd
    alors la valeur par défaut.

## Renommer un paramètre

Le nom exposé diffère parfois du nom PHP :

```php
<?php

$app->get('/items', function (#[Query('q', min_length: 2)] ?string $recherche = null) {
    return ['recherche' => $recherche];
});
// ?q=clavier  -> recherche = "clavier"
```

Le premier argument de l'attribut est le nom sur le fil ; `name:` est un alias
nommé équivalent.

## Booléens et tableaux

Les valeurs HTTP sont des chaînes, coercées vers le type déclaré :

```php
<?php

$app->get('/items', function (
    #[Query] bool $actif = false,
    #[Query] array $tags = [],
) {
    return ['actif' => $actif, 'tags' => $tags];
});
```

```console
$ curl "http://127.0.0.1:8000/items?actif=1&tags=a&tags=b"
{"actif":true,"tags":["a","b"]}
```

Un tableau peut aussi être validé élément par élément avec
`#[Query] array $ids` puis un modèle, ou avec les validateurs Svinx.

## Validation

Les contraintes du paragraphe précédent s'appliquent de la même façon :

```php
<?php

$app->get('/items', function (
    #[Query(gte: 1, le: 100)] int $limit = 10,
) {
    return ['limit' => $limit];
});
```

```console
$ curl -i "http://127.0.0.1:8000/items?limit=0"
HTTP/1.1 422 Unprocessable Entity
{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["query","limit"],"type":"value_error","msg":"Le paramètre query 'limit' doit être >= 1"}]}
```

Voir [Validations](validations.md) pour la liste complète des contraintes et des
types.

## Query string brute

Pour lire la chaîne telle quelle (par exemple pour une signature) :

```php
<?php

use Eorbahapi\Request;

$app->get('/webhook', function (Request $request) {
    return ['query' => $request->query_string()];
});
```

`$request->query()` retourne le tableau complet ; `$request->query('clé')` une
seule valeur.

## Récapitulatif

- `#[Query]` lit un paramètre de la query string ;
- une valeur par défaut ou un type nullable rend le paramètre optionnel ;
- `#[Query('nom')]` renomme le paramètre exposé ;
- booléens et tableaux sont coercés depuis les chaînes ;
- les contraintes produisent des 422 localisées (`["query","nom"]`).

[Suivant : Validations](validations.md){ .md-button }
