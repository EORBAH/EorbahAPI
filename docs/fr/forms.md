# Formulaires

## Ce que vous allez apprendre

Recevoir un formulaire `application/x-www-form-urlencoded` ou
`multipart/form-data`, valider ses champs, et le combiner avec les fichiers.

## Déclarer un champ de formulaire

`#[Form]` lit `$_POST` (ou le corps multipart) :

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Form;

$app = new EorbahAPI();

$app->post('/login', function (
    #[Form(min_length: 3)] string $user,
    #[Form(ge: 18, le: 130)] int $age,
) {
    return ['user' => $user, 'age' => $age];
});

$app->run();
```

```console
$ curl -X POST "http://127.0.0.1:8000/login" \
    -d 'user=oumar&age=27'
{"user":"oumar","age":27}
```

Les valeurs arrivent sous forme de chaînes ; la coercition laxiste convertit
`"27"` en `27` avant de vérifier `ge`/`le`.

## Un modèle de formulaire

Tout le formulaire peut être validé par un modèle Svinx :

```php
<?php

use Eorbahapi\Params\Form;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

final class Connexion extends BaseModel
{
    #[Field(min_length: 3)]
    public string $user;

    #[Field(min_length: 8)]
    public string $mot_de_passe;
}

$app->post('/login', function (#[Form] Connexion $formulaire) {
    return ['user' => $formulaire->user];
});
```

## Champs optionnels

```php
<?php

$app->post('/profil', function (
    #[Form(min_length: 2)] string $nom,
    #[Form(max_length: 200)] ?string $bio = null,
) {
    return ['nom' => $nom, 'bio' => $bio];
});
```

Un champ absent et optionnel vaut `null` ; un champ requis absent produit une
**422**.

## Renommer un champ

```php
<?php

$app->post('/contact', function (
    #[Form('e-mail', min_length: 5)] string $email,
) {
    return ['email' => $email];
});
// e-mail=jean@example.com
```

## Formulaire et fichiers

Un même endpoint peut recevoir des champs et des fichiers
(`multipart/form-data`) :

```php
<?php

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Params\File;
use Eorbahapi\Params\Form;

$app->post('/upload', function (
    #[Form(min_length: 2)] string $titre,
    #[File] UploadFile $document,
) {
    $contenu = $document->read();

    return ['titre' => $titre, 'octets' => strlen($contenu)];
});
```

Voir [Fichiers](files.md) pour `UploadFile` et les téléversements multiples.

## Lire le formulaire brut

Pour un traitement particulier :

```php
<?php

use Eorbahapi\Request;

$app->post('/form', function (Request $request) {
    return ['champs' => $request->post()];
});
```

## Récapitulatif

- `#[Form]` lit un champ du formulaire, avec coercition et validation ;
- un modèle Svinx peut valider tout le formulaire ;
- les champs optionnels se déclarent par défaut ou par type nullable ;
- `#[Form('nom')]` renomme le champ exposé ;
- `#[Form]` et `#[File]` se combinent pour les formulaires multipart.

[Suivant : Fichiers](files.md){ .md-button }
