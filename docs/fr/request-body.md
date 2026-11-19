# Corps de requête

## Ce que vous allez apprendre

Recevoir un corps JSON, le valider avec un modèle Svinx, extraire un seul champ
ou récupérer le payload brut.

## Un modèle de corps

Le cas courant : tout le corps JSON est validé par un modèle.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class Item extends BaseModel
{
    #[Field(min_length: 2, max_length: 80)]
    public string $nom;

    #[Field(gt: 0)]
    public float $prix;

    #[Field(ge: 0)]
    public int $stock = 0;

    public ?string $description = null;
}

$app = new EorbahAPI();

$app->post('/items', function (#[Body] Item $item) {
    return ['item' => $item->model_dump()];
});

$app->run();
```

```console
$ curl -X POST "http://127.0.0.1:8000/items" \
    -H 'Content-Type: application/json' \
    -d '{"nom":"Clavier","prix":49.9,"stock":12}'
{"item":{"nom":"Clavier","prix":49.9,"stock":12,"description":null}}
```

Le handler reçoit une instance d'`Item`, jamais un tableau brut : les champs
types, les valeurs par défaut et les contraintes sont déjà appliqués.

## Un seul champ du corps

Si le corps contient plusieurs champs et que vous n'en voulez qu'un :

```php
<?php

$app->post('/subscribe', function (
    #[Body(name: 'email', min_length: 5)] string $email,
) {
    return ['subscribed' => $email];
});
```

```json
{"email": "jean@example.com"}
```

Le champ est extrait du payload, puis validé ; s'il manque et qu'aucune valeur
par défaut n'existe, la réponse est une 422.

## Le corps complet brut

Pour transmettre le payload tel quel (webhook, agrégateur…) :

```php
<?php

$app->post('/webhook', function (#[Body] array $payload) {
    return ['received' => $payload];
});
```

`array` (et `mixed` / `iterable`) reçoit tout le corps décodé.

## Statut et en-têtes

Pour répondre avec un statut ou des en-têtes particuliers, retournez une
`JSONResponse` :

```php
<?php

use Eorbahapi\Responses\JSONResponse;

$app->post('/items', function (#[Body] Item $item) {
    return new JSONResponse(['item' => $item->model_dump()], 201, [
        'Location' => '/items/42',
    ]);
});
```

## Erreurs de validation

Un corps invalide produit une **422** avec le détail par champ :

```console
$ curl -i -X POST "http://127.0.0.1:8000/items" \
    -H 'Content-Type: application/json' -d '{"nom":"C","prix":0}'
HTTP/1.1 422 Unprocessable Entity
{"error":true,"status":422,"message":"Request validation error","details":[
  {"loc":["body","nom"],"type":"value_error","msg":"Le champ nom doit avoir au moins 2 caractères"},
  {"loc":["body","prix"],"type":"value_error","msg":"Le champ prix doit être > 0"}
]}
```

## `model_dump()` et sérialisation

Un modèle expose `model_dump()`, `model_dump_json()`, `model_validate()` et
`model_validate_json()` :

```php
<?php

$item = Item::model_validate(['nom' => 'Clavier', 'prix' => 49.9]);
$item->model_dump();                       // tableau PHP
$item->model_dump_json(JSON_PRETTY_PRINT); // JSON
```

Les valeurs par défaut des propriétés sont appliquées à la validation, et les
champs optionnels valent `null`.

!!! tip "Corps et query en même temps"
    Un handler peut cumuler `#[Body]`, `#[Path]`, `#[Query]` et `#[Header]` :
    chaque paramètre est résolu indépendamment.

## Récapitulatif

- `#[Body] Modele $x` valide tout le corps JSON avec un `BaseModel` ;
- `#[Body(name: 'champ')]` extrait un seul champ ;
- `#[Body] array` reçoit le payload brut ;
- les erreurs de validation sont des 422 détaillées par champ ;
- `model_dump()`/`model_validate()` sérialisent et relisent les modèles.

[Suivant : Modèles imbriqués](nested-models.md){ .md-button }
