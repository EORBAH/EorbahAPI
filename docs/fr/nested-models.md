# Modèles imbriqués

## Ce que vous allez apprendre

Composer des modèles : un modèle `Svinx\Validator` peut être la propriété d'un
autre modèle. Vous verrez la validation récursive, les alias (`full_name`),
les types dédiés comme `EmailStr`, et comment une erreur dans un sous-modèle
remonte en `422`.

## Le code

`docs_src/NestedBodyExample.php` définit deux modèles, puis une route :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\Validators\EmailStr;

/**
 * Adresse, utilisée comme modèle imbriqué.
 */
final class Address extends BaseModel
{
    #[Field(min_length: 2)]
    public string $city;

    #[Field(pattern: '/^[0-9]{5}$/')]
    public string $zip;
}
```

`Address` est un modèle ordinaire. Rien ne le destine encore à être imbriqué :
c'est la déclaration ci-dessous qui fait le lien.

```php
/**
 * Client : un modèle qui en contient un autre.
 */
final class Customer extends BaseModel
{
    #[Field(alias: 'full_name', min_length: 2, max_length: 40)]
    public string $name;

    public EmailStr $email;

    #[Field(ge: 18, le: 130)]
    public int $age = 18;

    public Address $address;
}
```

```php
/**
 * Modèles imbriqués : un modèle dans un autre modèle.
 */
final class NestedBodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->post('/customers', function (#[Body] Customer $customer) {
            return ['customer' => $customer->model_dump()];
        });

        return $app;
    }
}
```

Le lien entre les deux modèles est le **type de la propriété** : parce que
`$address` est déclaré `Address`, et `Address` étend `BaseModel`, le validateur
construit récursivement un modèle à partir de l'objet JSON `address`. Il n'y a
aucune configuration à écrire.

Trois choses valent d'être soulignées dans `Customer` :

- `#[Field(alias: 'full_name')]` : le JSON utilise `full_name`, la propriété
  PHP reste `$name` — plus lisible des deux côtés ;
- `public EmailStr $email;` : pas besoin de `#[Field]`, le type
  `Svinx\Validator\Validators\EmailStr` valide l'adresse à lui seul ;
- `#[Field(pattern: '/^[0-9]{5}$/')]` sur `zip` : cinq chiffres, exactement.

`model_dump()` traverse les frontières du modèle : il ressort l'alias
`full_name`, l'âge, et l'objet `address` complet.

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `POST /customers` · `{"full_name":"Jean Dupont","email":"jean@example.com","age":30,"address":{"city":"Paris","zip":"75001"}}` | 200 | `{"customer":{"full_name":"Jean Dupont","email":"jean@example.com","age":30,"address":{"city":"Paris","zip":"75001"}}}` |
| `POST /customers` · même corps avec `"zip":"75"` | 422 | `{"error":true,…,"details":[{"loc":["body","customer"],"type":"value_error","msg":"Le champ 'zip' ne correspond pas au pattern attendu"}]}` |

Dans les deux cas, `loc` vaut `["body", "customer"]` : l'erreur est imputée au
paramètre, et `msg` nomme le champ fautif — ici `zip`, un champ du **sous-modèle**.

!!! note "Ce que le sous-modèle ajoute"
    Quelques variantes utiles, toutes en `422` :

    - `zip` absent de `address` : `Le champ 'zip' est requis.` ;
    - `address` absent : `Le champ 'address' est requis.` ;
    - `email` mal formé : `EmailStr invalide : … n'est pas une adresse email valide.` ;
    - `age` à `12` : `Le champ 'age' doit être >= 18`.

    À l'inverse, `age` absent donne `18` (valeur par défaut de la propriété) :
    la réponse contient alors `"age":18` même si le JSON ne l'envoyait pas.
    La requête qui envoie `"name"` au lieu de `"full_name"` passe aussi, car
    `populate_by_name` est actif par défaut : l'alias est simplement prioritaire.

!!! tip "Modèle imbriqué ou modèle séparé ?"
    Découpez tôt : un sous-modèle comme `Address` est réutilisable dans
    `Customer`, `Supplier`, `Invoice`… Chaque niveau garde ses propres
    contraintes et ses propres messages, et vous pouvez tester `Address` seul
    avec `Address::model_validate([...])` ou `model_validate_json()`.

## Récapitulatif

- une propriété typée par un `BaseModel` déclenche la validation récursive ;
- `#[Field(alias: 'full_name')]` découple le nom sur le fil et le nom de propriété ;
- `EmailStr` et `pattern` valident sans `#[Field]` ou presque ;
- une erreur dans un sous-modèle remonte en `422` avec `loc = [body, paramètre]`
  et `msg` nommant le champ fautif ;
- `model_dump()` sérialise en profondeur, alias compris.

[Suivant : Formulaires](forms.md){ .md-button }
