# Validations

## Ce que vous allez apprendre

Aller au-delà du typage : contraindre une valeur numérique, une chaîne, une
liste, définir des valeurs autorisées, utiliser les validateurs Svinx prêts à
l'emploi et écrire vos propres validations.

## Contraintes de base

Les attributs de paramètre héritent de `FieldInfo` ; les mêmes contraintes
existent sur les champs de modèle (`#[Field]`) :

| Contrainte | Types | Effet |
| --- | --- | --- |
| `gt` / `gte` | numériques | supérieur à / supérieur ou égal |
| `lt` / `le` | numériques | inférieur à / inférieur ou égal |
| `multiple_of` | numériques | multiple de |
| `min_length` / `max_length` | chaînes, listes | longueur bornée |
| `pattern` | chaînes | expression régulière |
| `choices` | tous | valeurs autorisées |
| `default` / `default_factory` | tous | valeur par défaut |

```php
<?php

use Eorbahapi\Params\Query;

$app->get('/items', function (
    #[Query(gte: 1, le: 100)] int $limit = 10,
    #[Query(min_length: 2, max_length: 40)] ?string $q = null,
    #[Query(pattern: '/^[a-z0-9-]+$/')] ?string $slug = null,
    #[Query(choices: ['asc', 'desc'])] string $tri = 'asc',
) {
    return compact('limit', 'q', 'slug', 'tri');
});
```

## Types validés

Svinx\Validator fournit des types qui portent leur propre format et leur propre
validation :

```php
<?php

use Svinx\Validator\Validators\EmailStr;
use Svinx\Validator\Validators\UUID;
use Svinx\Validator\Validators\HttpUrl;

$app->get('/compte', function (
    #[Query] EmailStr $email,
    #[Query] ?UUID $id = null,
    #[Query] ?HttpUrl $site = null,
) {
    return ['email' => (string) $email, 'id' => $id, 'site' => $site];
});
```

Disponibles : `EmailStr`, `HttpUrl`, `UUID`, `Date`, `Datetime`, `Decimal`,
`IPv4Address`, `IPv6Address`, `SecretStr`.

Une valeur invalide produit un 422 ; le message indique le format attendu.

## Coercition laxiste

Les valeurs HTTP sont des chaînes : les attributs de paramètre activent la
coercition (`"42"` → `42`, `"true"` → `true`). Une valeur non convertible échoue
proprement au lieu de produire un type inattendu.

## Modèles Svinx

Pour valider un objet complet, utilisez un `BaseModel` (voir
[Corps de requête](request-body.md)) :

```php
<?php

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class Item extends BaseModel
{
    #[Field(min_length: 2)]
    public string $nom;

    #[Field(gt: 0)]
    public float $prix;

    #[Field(ge: 0, le: 1000)]
    public int $stock = 0;

    #[Field(pattern: '/^[a-z0-9-]+$/')]
    public ?string $slug = null;
}
```

`Item::model_validate($donnees)` lève une `ValidationError` si les règles ne sont
pas respectées.

## Validateurs personnalisés

`#[FieldValidator]` cible un ou plusieurs champs et déclare une méthode publique
qui reçoit la valeur (sous forme de chaîne lors de la validation) et retourne la
valeur éventuellement transformée :

```php
<?php

use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use Svinx\Validator\FieldValidator;
use Svinx\Validator\ValidationError;

final class Compte extends BaseModel
{
    #[Field(min_length: 8)]
    public string $mot_de_passe;

    #[FieldValidator('mot_de_passe')]
    public function validate_mot_de_passe(string $cls, string $value): string
    {
        if (!preg_match('/[0-9]/', $value)) {
            throw new ValidationError('Le mot de passe doit contenir un chiffre.');
        }

        return $value;
    }
}
```

Options utiles :

- `#[FieldValidator(mode: FieldValidator::BEFORE)]` s'exécute avant le contrôle
  de type (pratique pour convertir une chaîne en objet, par exemple un
  `DateTime`) ;
- `#[FieldValidator(FieldValidator::ANY)]` s'applique à tous les champs ;
- une cible peut être un tableau (`['nickname', 'age']`) ou déduite du nom de la
  méthode (`validate_username` → champ `username`) ;
- `always: true` exécute le validateur même lorsque la valeur est `null`.

## Erreurs de validation

Toutes les erreurs de requête sont converties en `RequestValidationError`
(statut **422**) :

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

`details[].loc` indique l'emplacement (`path`, `query`, `header`, `cookie`,
`body`, `form`, `file`) et le nom du paramètre. Le gestionnaire par défaut est
documenté dans [Gestion des erreurs](errors.md).

## Récapitulatif

- les contraintes viennent de `FieldInfo` et se déclarent sur les attributs ou
  les champs de modèle ;
- les types validés (`EmailStr`, `UUID`, `Date`…) contrôlent le format ;
- la coercition laxiste convertit les chaînes HTTP ;
- un `BaseModel` valide un objet complet ;
- `FieldValidator` ajoute des règles métier ;
- toutes les erreurs deviennent des 422 avec des `details` localisées.

[Suivant : Corps de requête](request-body.md){ .md-button }
