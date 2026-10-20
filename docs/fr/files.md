# Fichiers

## Ce que vous allez apprendre

Recevoir des téléversements (`multipart/form-data`) avec `#[File]` : obtenir un
objet `UploadFile` typé et lisible en flux, ou l'entrée brute de `$_FILES`, et
comprendre comment un échec de téléversement devient un `422`.

## Le code

`docs_src/FilesExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\File;

/**
 * Téléversement de fichiers (`multipart/form-data`).
 */
final class FilesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Le type déclaré `UploadFile` construit l'objet depuis $_FILES.
        $app->post('/upload', function (#[File] UploadFile $avatar) {
            $content = $avatar->read(64);

            return [
                'filename' => $avatar->filename,
                'content_type' => $avatar->content_type,
                'preview' => substr($content, 0, 5),
            ];
        });

        // Sans conversion : l'entrée brute de $_FILES est transmise.
        $app->post('/meta', function (#[File('document')] array $document) {
            return ['name' => $document['name'], 'size' => $document['size']];
        });

        return $app;
    }
}
```

`#[File]` vient de `Eorbahapi\Params\File` et lit `$_FILES` (via
`Request::file()`). Le nom du champ sur le fil est le nom du paramètre PHP ;
`#[File('document')]` le renomme.

Le comportement dépend, là encore, du **type déclaré** :

| Type déclaré | Ce que le handler reçoit |
| --- | --- |
| `Eorbahapi\Datastructures\UploadFile` | un objet, flux déjà ouvert en lecture |
| `array` (ou autre) | l'entrée brute : `name`, `type`, `tmp_name`, `error`, `size` |

`UploadFile` est volontairement minimal :

| Membre | Rôle |
| --- | --- |
| `$filename` | nom d'origine envoyé par le client |
| `$content_type` | type MIME déclaré par le client |
| `read($length = null)` | lit `$length` octets (8192 par défaut) |
| `write($data)` | écrit dans le flux |
| `seek($offset, $whence = SEEK_SET)` | repositionne le curseur |
| `close()` | ferme le flux |

Vous lisez donc le fichier **en flux** : inutile de charger un gros fichier en
mémoire, `read(64)` se contente des 64 premiers octets. Le flux étant
déplaçable, `seek(0)` permet de relire depuis le début après un premier `read()`.

## Ce qui se passe

| Requête | Statut | Corps |
| --- | --- | --- |
| `POST /upload` · fichier `avatar` (`photo.png`, `image/png`) | 200 | `{"filename":"photo.png","content_type":"image/png","preview":"<?php"}` |
| `POST /meta` · fichier `document` (`rapport.pdf`) | 200 | `{"name":"rapport.pdf","size":1024}` |
| `POST /upload` · aucun fichier | 422 | `{"error":true,…,"details":[{"loc":["file","avatar"],"type":"missing"}]}` |

Le `preview` vaut `<?php` parce que le scénario de test téléverse le fichier de
l'exemple lui-même : les cinq premiers octets sont ceux de l'en-tête PHP. C'est
un raccourci de démonstration — le framework, lui, se contente de lire ce que le
client envoie.

!!! warning "Un `UPLOAD_ERR_*` devient un 422 de type `file`"
    Avant d'ouvrir le flux, le résolveur contrôle `error` dans `$_FILES`.
    `UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE`, `UPLOAD_ERR_PARTIAL`… sont
    convertis en `422` avec `"type": "file"` et un message du type
    `Le fichier 'avatar' n'a pas été téléversé correctement (code 1).` Un
    `tmp_name` illisible donne la même famille d'erreur :
    `Le fichier 'avatar' est illisible.` Le handler n'est jamais appelé.

!!! note "Le tableau brut ne vérifie rien"
    Avec `#[File('document')] array $document`, l'entrée de `$_FILES` est
    transmise telle quelle : `error` n'est pas contrôlé. C'est pratique pour un
    téléversement multiple (le tableau d'entrées est conservé), mais c'est à vous
    de tester `$document['error']` et de refuser les fichiers en erreur.

## Récapitulatif

- `#[File]` (`Eorbahapi\Params\File`) lit `$_FILES`, en `multipart/form-data` ;
- `#[File] UploadFile $avatar` construit un objet avec `$filename`,
  `$content_type` et un flux ouvert ;
- `read()`, `write()`, `seek()`, `close()` travaillent sur le flux, sans tout
  charger en mémoire ;
- `#[File('document')] array $document` donne l'entrée brute de `$_FILES` ;
- un code `UPLOAD_ERR_*` produit un `422` avec `loc = [file, …]` et
  `type: file` ;
- `#[File]` étend `#[Form]`, qui étend `#[Body]` : un même handler peut cumuler
  champs de formulaire et fichiers.

[Suivant : En-têtes](headers.md){ .md-button }
