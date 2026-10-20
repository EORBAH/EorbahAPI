# Corps de requête

## Ce que vous allez apprendre

Recevoir des données JSON : faire valider **tout** le corps par un modèle
`Svinx\Validator`, extraire **un seul champ** du corps, récupérer le corps
**brut**, et voir comment une validation qui échoue devient une réponse `422`.

## Le code

`docs_src/BodyExample.php` déclare d'abord un modèle :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Modèle de corps validé par Svinx\Validator.
 */
final class Product extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;

    #[Field(ge: 0, le: 1000)]
    public int $stock = 0;
}
```

Puis l'application, avec les trois façons de lire le corps :

```php
/**
 * Corps de requête : validation d'un modèle complet ou d'un champ unique.
 */
final class BodyExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Tout le corps JSON est validé par le modèle.
        $app->post('/products', function (#[Body] Product $product) {
            return ['product' => $product->model_dump()];
        });

        // Un seul champ du corps, extrait par son nom.
        $app->post('/subscribe', function (#[Body(name: 'email', min_length: 5)] string $email) {
            return ['subscribed' => $email];
        });

        // Le corps complet, brut.
        $app->post('/echo', function (#[Body] array $payload) {
            return ['received' => $payload];
        });

        return $app;
    }
}
```

`#[Body]` ne fait qu'une chose : indiquer **où** lire la valeur
(`ParamTypes::BODY`). Ce que le paramètre reçoit dépend de son **type déclaré** :

| Type déclaré | Ce qui est transmis |
| --- | --- |
| un modèle `Svinx\Validator\BaseModel` | tout le corps, validé par le modèle |
| un scalaire (`string`, `int`…) | le champ portant le nom du paramètre |
| `array` / `mixed` | tout le corps, sans validation |

`#[Field]` porte les contraintes, appliquées dans l'ordre du modèle :

| Contrainte | Effet |
| --- | --- |
| `min_length` / `max_length` | longueur d'une chaîne |
| `gt` / `ge` | strictement supérieur / supérieur ou égal |
| `lt` / `le` | strictement inférieur / inférieur ou égal |
| `pattern` | expression régulière |
| `choices` | liste fermée de valeurs |
| `alias` | nom exposé, différent du nom de propriété |
| `default` | valeur de repli si la clé est absente |

Le type PHP de la propriété fait aussi partie du contrat : `public float $price`
coerce `49` en `49.0`, et `public int $stock = 0` fournit `0` quand la clé
`stock` est absente du JSON.

`model_dump()` sérialise le modèle en tableau, en réutilisant les alias et en
descendant récursivement dans les modèles imbriqués — c'est exactement ce que
vous pouvez retourner depuis un handler.

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `POST /products` · `{"name":"Clavier","price":49.9,"stock":12}` | 200 | `{"product":{"name":"Clavier","price":49.9,"stock":12}}` |
| `POST /products` · `{"name":"C","price":0}` | 422 | `{"error":true,…,"details":[{"loc":["body","product"],"type":"value_error"}]}` |
| `POST /subscribe` · `{"email":"jean@example.com"}` | 200 | `{"subscribed":"jean@example.com"}` |
| `POST /subscribe` · `{"email":"x"}` | 422 | `{"error":true,…,"details":[{"loc":["body","email"],"type":"value_error"}]}` |
| `POST /echo` · `{"a":1,"b":[2,3]}` | 200 | `{"received":{"a":1,"b":[2,3]}}` |

L'enveloppe d'erreur est toujours la même ; voici la première ligne du tableau
en détail :

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [
    {
      "loc": ["body", "product"],
      "type": "value_error",
      "msg": "Le champ 'name' doit avoir au moins 2 caractères"
    }
  ]
}
```

`loc` désigne l'emplacement (`body`) et le nom du paramètre fautif (`product`,
`email`). Pour un modèle, c'est le nom du **paramètre**, pas le champ : le détail
du champ est dans `msg`, produit par `Svinx\Validator`.

!!! tip "Content-Type: application/json"
    Avec `curl`, envoyez `-H 'Content-Type: application/json' -d '{…}'`. Le
    framework décode le corps JSON une seule fois ; si le JSON est vide ou
    invalide, il retombe sur `$_POST` — pratique pour les formulaires
    traditionnels, mais ne comptez pas dessus pour du JSON strict.

!!! warning "Un modèle invalide n'est jamais un 400"
    Une erreur de validation Svinx devient un `422 Unprocessable Entity`, pas un
    `400`. Un `400` signale un corps illisible ; un `422` signale un corps lisible
    mais inacceptable. Le handler n'est alors **jamais appelé**.

## Récapitulatif

- `#[Body]` sur un modèle `BaseModel` : tout le corps est validé par le modèle ;
- `#[Body(name: 'email', min_length: 5)] string $email` : un seul champ extrait
  et validé avec les contraintes de l'attribut ;
- `#[Body] array $payload` : le corps brut, sans validation ;
- `#[Field]` porte les contraintes (`min_length`, `gt`, `ge`, `le`, `pattern`,
  `choices`, `alias`, `default`) ;
- le type déclaré décide de la coercition, `model_dump()` de la sérialisation ;
- toute `ValidationError` de Svinx est convertie en `422` avec `loc`, `type` et `msg`.

[Suivant : Modèles imbriqués](nested-models.md){ .md-button }
