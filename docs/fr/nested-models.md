# Modèles imbriqués

## Ce que vous allez apprendre

Composer des modèles : un objet dans un objet, une liste de modèles, un modèle
optionnel, et les options de configuration d'un modèle Svinx.

## Un modèle dans un modèle

Un modèle peut déclarer une propriété dont le type est un autre `BaseModel` :

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class Adresse extends BaseModel
{
    #[Field(min_length: 2)]
    public string $ville;

    #[Field(pattern: '/^[0-9]{5}$/')]
    public string $code_postal;
}

final class Client extends BaseModel
{
    #[Field(min_length: 2)]
    public string $nom;

    public Adresse $adresse;
}

$app = new EorbahAPI();

$app->post('/clients', function (#[Body] Client $client) {
    return ['client' => $client->model_dump()];
});
```

```console
$ curl -X POST "http://127.0.0.1:8000/clients" \
    -H 'Content-Type: application/json' \
    -d '{"nom":"Oumar","adresse":{"ville":"Paris","code_postal":"75001"}}'
{"client":{"nom":"Oumar","adresse":{"ville":"Paris","code_postal":"75001"}}}
```

L'erreur d'un sous-champ remonte avec son chemin complet
(`loc: ["body", "adresse", "code_postal"]`).

## Une liste de modèles

PHP ne permet pas de typer `array` par ses éléments ; utilisez un `TypeAdapter`
pour valider une liste de modèles :

```php
<?php

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\TypeAdapter;
use Svinx\Validator\Typing\Type;

final class Ligne extends BaseModel
{
    #[Field(min_length: 1)]
    public string $produit;

    #[Field(gt: 0)]
    public int $quantite;
}

$lignes = TypeAdapter::of(Type::list(Type::model(Ligne::class)));

$valides = $lignes->validate([
    ['produit' => 'Clavier', 'quantite' => 2],
    ['produit' => 'Souris', 'quantite' => 1],
]);
// $valides contient des instances de Ligne
```

La même liste peut entrer dans un modèle avec un champ `array`, puis être
validée dans un `#[FieldValidator]` ou dans le handler.

## Modèle optionnel

Un modèle nullable accepte `null` :

```php
<?php

final class Profil extends BaseModel
{
    public ?Adresse $adresse = null;
}
```

Sans `null`, la clé `adresse` est requise : une requête qui l'omet produit un
422.

## Alias et configuration

`Field(alias: ...)` expose un autre nom sur le fil ; `Config` règle le
comportement global du modèle :

```php
<?php

use Svinx\Validator\Config;

final class Produit extends BaseModel
{
    public static function model_config(): Config
    {
        return new Config(
            extra: Config::EXTRA_FORBID,        // refuse les champs inconnus
            str_strip_whitespace: true,         // nettoie les chaînes
            alias_generator: static fn (string $champ): string => strtolower($champ),
        );
    }

    #[Field(min_length: 2)]
    public string $Nom;
}
```

Options principales de `Config` :

| Option | Effet |
| --- | --- |
| `extra` | `ignore` (défaut), `forbid`, `allow` |
| `populate_by_name` | accepte le nom de propriété et l'alias |
| `alias_generator` | callable `(string $champ): string` |
| `str_strip_whitespace` | retire les espaces autour des chaînes |
| `str_to_lower` / `str_to_upper` | normalise la casse |
| `title` | titre du modèle dans le schéma JSON |

## Schéma du modèle

`model_schema()` retourne un schéma JSON exploitable (et utilisé par OpenAPI) :

```php
<?php

Client::model_schema();
// ['title' => 'Client', 'type' => 'object', 'properties' => [...], 'required' => [...]]
```

Les modèles imbriqués y apparaissent comme des objets ou des références selon
le contexte.

!!! tip "Documentation automatique"
    Un modèle imbriqué devient automatiquement un composant
    `components.schemas` dans la documentation OpenAPI — voir
    [Documentation OpenAPI](openapi.md).

## Récapitulatif

- une propriété typée `BaseModel` est validée récursivement ;
- les listes de modèles se valident avec `TypeAdapter` et `Type::list()` ;
- `?Adresse` rend la sous-structure optionnelle ;
- `Config` règle les champs inconnus, les alias et la normalisation ;
- `model_schema()` décrit le modèle pour la documentation.

[Suivant : Formulaires](forms.md){ .md-button }
