# Formulaires

## Ce que vous allez apprendre

Lire un formulaire `application/x-www-form-urlencoded` ou
`multipart/form-data` avec `#[Form]` : les valeurs arrivent en **chaînes** et
sont coercées avant validation, exactement comme les paramètres de requête.

## Le code

`docs_src/FormExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Form;

/**
 * Données de formulaire (`application/x-www-form-urlencoded`).
 */
final class FormExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Chaque champ est coercé puis validé.
        $app->post('/login', function (
            #[Form(min_length: 3)] string $username,
            #[Form(min_length: 6)] string $password,
        ) {
            return ['username' => $username, 'password_length' => strlen($password)];
        });

        // Un modèle peut recevoir un formulaire complet.
        $app->post('/profile', function (
            #[Form(ge: 18, le: 130)] int $age,
            #[Form(choices: ['fr', 'en'])] string $lang = 'fr',
        ) {
            return ['age' => $age, 'lang' => $lang];
        });

        return $app;
    }
}
```

`#[Form]` vient de `Eorbahapi\Params\Form` et lit `$_POST` (via
`Request::post()`). Le nom du champ sur le fil est le nom du paramètre PHP ;
`#[Form(name: 'e-mail')]` suffit à en changer.

La coercition est ici le point central. Un formulaire ne transporte que du
texte : `age=30` arrive sous la forme `'30'`. Le résolveur construit le type
déclaré (`int`) en mode **laxiste** et laisse Svinx convertir, puis appliquer
les contraintes de l'attribut :

| Envoyé | Reçu dans le handler |
| --- | --- |
| `age=30` | `30` (`int`) |
| `age=abc` | `422` — coercition impossible |
| `lang=en` | `'en'` |
| `lang` absent | `'fr'`, la valeur par défaut PHP |

Les contraintes disponibles sont celles de tout paramètre : `min_length`,
`max_length`, `pattern`, `choices`, `ge` / `le`, `gt` / `lt`.

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `POST /login` · `username=oumar&password=secret123` | 200 | `{"username":"oumar","password_length":9}` |
| `POST /login` · `username=ou&password=secret123` | 422 | `{"error":true,…,"details":[{"loc":["form","username"],"type":"value_error"}]}` |
| `POST /profile` · `age=30&lang=en` | 200 | `{"age":30,"lang":"en"}` |
| `POST /profile` · `age=12` | 422 | `{"error":true,…,"details":[{"loc":["form","age"],"type":"value_error"}]}` |

Le `loc` commence par `form`, et non par `body` : c'est ce qui distingue une
donnée de formulaire d'une donnée JSON dans le rapport d'erreur. Le message
correspondant, pour `age=12`, est
`Le paramètre form 'age' doit être >= 18`.

:::callout{type="warning" title="`#[Body]` ne lit pas les formulaires, et réciproquement"}
`#[Body]` lit le corps JSON (`php://input` décodé), `#[Form]` lit `$_POST`.
Envoyer du JSON à `/profile` ne donne donc pas `30` mais une erreur `422`
de type `missing` : `Le paramètre form 'age' est requis.` Choisissez
l'attribut qui correspond au `Content-Type` envoyé par le client.
:::

:::callout{type="info" title="`#[File]` étend `#[Form]`"}
La hiérarchie est `Param` → `Body` → `Form` → `File`. Un paramètre
`#[File]` accepte donc les mêmes contraintes qu'un paramètre `#[Form]`, et
un formulaire `multipart/form-data` peut mélanger champs texte (`#[Form]`)
et fichiers (`#[File]`) dans le même handler — voir la page suivante.
:::

## Récapitulatif

- `#[Form]` (`Eorbahapi\Params\Form`) lit `$_POST` ;
- les valeurs arrivent en chaînes et sont coercées vers le type déclaré :
  `'30'` devient `30` ;
- les contraintes de l'attribut s'appliquent après coercition
  (`min_length`, `ge` / `le`, `choices`…) ;
- `loc` vaut `form` dans les erreurs `422` ;
- `#[Body]` (JSON) et `#[Form]` (formulaire) sont deux sources distinctes ;
- `#[File]` étend `#[Form]`.

[Suivant : Fichiers](#/fr/files)
