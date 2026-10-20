# Validations

## Ce que vous allez apprendre

Poser des contraintes sur un paramètre (`ge`, `min_length`, `pattern`,
`choices`…), valider un type Svinx comme `EmailStr`, et lire sans hésiter la
réponse `422` : ce que contient `details`, et ce que change le type d'erreur
`value_error` ou `missing`.

## Le code

`docs_src/QueryValidationsExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Query;
use Svinx\Validator\Validators\EmailStr;

final class QueryValidationsExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Bornes numériques : ge = >=, le = <=, gt = >, lt = <.
        $app->get('/limit', function (#[Query(ge: 1, le: 100)] int $limit = 10) {
            return ['limit' => $limit];
        });

        // Longueur et expression régulière sur une chaîne.
        $app->get('/slug', function (
            #[Query('slug', min_length: 3, max_length: 20, pattern: '/^[a-z0-9-]+$/')] string $value
        ) {
            return ['slug' => $value];
        });

        // Liste fermée de valeurs autorisées.
        $app->get('/sort', function (#[Query(choices: ['asc', 'desc'])] string $order = 'asc') {
            return ['order' => $order];
        });

        // Type validé de Svinx : EmailStr.
        $app->get('/email', function (#[Query] EmailStr $email) {
            return ['email' => $email->dump()];
        });

        // Valeur par défaut portée par l'attribut (sans défaut PHP).
        $app->get('/page', function (#[Query(default: 1, ge: 1)] int $page) {
            return ['page' => $page];
        });

        return $app;
    }
}
```

## Ce qui se passe

Les attributs `Path`, `Query`, `Header`, `Cookie`, `Body`, `Form` et `File`
étendent tous `Eorbahapi\Params\Param`, qui étend `Svinx\Validator\FieldInfo` :
il n'y a donc **qu'une seule grammaire de validation** dans le framework.

| Contrainte | Effet |
| --- | --- |
| `ge` / `gt` | valeur >= / > à la borne |
| `le` / `lt` | valeur <= / < à la borne |
| `multiple_of` | valeur multiple de la borne |
| `min_length` / `max_length` | longueur minimale / maximale d'une chaîne ou d'une liste |
| `pattern` | expression régulière (forme `/…/`), testée sur la chaîne entière |
| `choices` | liste fermée de valeurs autorisées |
| `default` / `default_factory` | valeur utilisée si le paramètre est absent (valeur ou callable) |
| `alias` / nom | nom du paramètre sur le fil, quand il diffère du paramètre PHP |
| `title` / `description` | documentation du paramètre, reprise dans la référence API |

Un type Svinx (`EmailStr`, `UUID`…) se pose directement comme type du paramètre.

| Type (`Svinx\Validator\Validators`) | Vérifie |
| --- | --- |
| `EmailStr` / `HttpUrl` | adresse email valide / URL HTTP(S) valide |
| `UUID` | identifiant UUID |
| `Date` / `Datetime` | date, horodatage |
| `Decimal` | décimal à précision exacte |
| `IPv4Address` / `IPv6Address` | adresse IP |
| `SecretStr` | secret masqué à l'affichage |

Comme `EmailStr` n'est pas un `string`, son résultat se relit avec `->dump()` :
c'est ce que fait `/email` pour renvoyer `"jean@example.com"` en JSON.

`default:` n'est pas un doublon du défaut PHP : `#[Query(default: 1)] int $page`
répond `{"page":1}` bien que le paramètre PHP n'ait aucune valeur par défaut. Le
défaut de l'attribut est essayé avant celui de PHP.

### Les scénarios de l'exemple

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/limit?limit=42', json: ['limit' => 42]),
        self::scenario('GET', '/limit?limit=0', status: 422, contains: 'value_error'),
        self::scenario('GET', '/limit?limit=101', status: 422, contains: 'value_error'),
        self::scenario('GET', '/slug?slug=mon-article', json: ['slug' => 'mon-article']),
        self::scenario('GET', '/slug?slug=Mon_Article', status: 422, contains: 'value_error'),
        self::scenario('GET', '/sort?order=desc', json: ['order' => 'desc']),
        self::scenario('GET', '/sort?order=random', status: 422, contains: 'value_error'),
        self::scenario('GET', '/email?email=jean@example.com', json: ['email' => 'jean@example.com']),
        self::scenario('GET', '/email?email=invalide', status: 422, contains: 'EmailStr'),
        self::scenario('GET', '/page', json: ['page' => 1]),
        self::scenario('GET', '/page?page=3', json: ['page' => 3]),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /limit?limit=42` | 200 | `{"limit":42}` |
| `GET /limit?limit=0` | 422 | `value_error` : la valeur doit être >= 1 |
| `GET /limit?limit=101` | 422 | `value_error` : la valeur doit être <= 100 |
| `GET /slug?slug=mon-article` | 200 | `{"slug":"mon-article"}` |
| `GET /slug?slug=Mon_Article` | 422 | `value_error` : le motif `^[a-z0-9-]+$` n'est pas respecté |
| `GET /sort?order=desc` | 200 | `{"order":"desc"}` |
| `GET /sort?order=random` | 422 | `value_error` : une des valeurs `asc, desc` est attendue |
| `GET /email?email=jean@example.com` | 200 | `{"email":"jean@example.com"}` |
| `GET /email?email=invalide` | 422 | `value_error` : « EmailStr invalide : 'invalide' n'est pas une adresse email valide. » |
| `GET /page` | 200 | `{"page":1}` |
| `GET /page?page=3` | 200 | `{"page":3}` |

### La forme d'une erreur 422

Toute erreur de validation produit la même enveloppe :

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [
    {"loc": ["query", "limit"], "type": "value_error", "msg": "Le paramètre query 'limit' doit être >= 1"}
  ]
}
```

`loc` est un couple : l'emplacement (`query`, `path`, `header`…) puis le nom du
paramètre **tel qu'il apparaît sur le fil**. `type` distingue deux familles :
`value_error` (valeur présente mais invalide) et `missing` (valeur absente et
obligatoire). `msg` est la phrase de Svinx, en français.

!!! note "Le handler n'est jamais appelé sur une erreur de validation"
    La résolution des paramètres a lieu avant l'invocation : si une contrainte
    échoue, la fonction n'est pas exécutée et une seule violation suffit à
    produire la réponse `422` — vous n'avez aucun test `null` à écrire.

!!! tip "Ancrez vos motifs"
    `pattern` est testé sur la chaîne **entière** : `/^[a-z0-9-]+$/` est correct,
    `/[a-z0-9-]+/` accepterait `Mon_Article` en le trouvant quelque part.

## Récapitulatif

- `Eorbahapi\Params\Param` étend `Svinx\Validator\FieldInfo` : mêmes contraintes partout ;
- numériques : `ge`, `gt`, `le`, `lt`, `multiple_of` ;
- chaînes et listes : `min_length`, `max_length`, `pattern`, `choices` ;
- valeurs : `default`, `default_factory`, `alias` (nom sur le fil), `title`, `description` ;
- les types Svinx (`EmailStr`, `HttpUrl`, `UUID`, `Date`, `Datetime`, `Decimal`, `IPv4Address`, `IPv6Address`, `SecretStr`) valident la valeur eux-mêmes ;
- une erreur donne `422` avec `{"error":true,"status":422,"message":"Request validation error","details":[{"loc":["query","limit"],"type":"value_error","msg":"…"}]}`, où `type` vaut `value_error` (valeur invalide) ou `missing` (paramètre obligatoire absent).

[Suivant : Corps de requête](request-body.md){ .md-button }
