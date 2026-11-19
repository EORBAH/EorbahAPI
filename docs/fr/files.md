# Fichiers

## Ce que vous allez apprendre

Recevoir un fichier téléversé avec `#[File]`, utiliser `UploadFile`, gérer
plusieurs fichiers et transmettre un téléversement brut.

## Un fichier unique

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\File;

$app = new EorbahAPI();

$app->post('/upload', function (#[File] UploadFile $fichier) {
    return [
        'nom' => $fichier->filename,
        'type' => $fichier->content_type,
        'contenu' => $fichier->read(),
    ];
});

$app->run();
```

```console
$ curl -X POST "http://127.0.0.1:8000/upload" \
    -F "fichier=@note.txt"
{"nom":"note.txt","type":"text/plain","contenu":"bonjour"}
```

Le fichier est lu depuis `$_FILES` et ouvert en lecture ; le handler reçoit un
objet `UploadFile`.

## L'API `UploadFile`

| Membre | Rôle |
| --- | --- |
| `filename` | nom d'origine |
| `content_type` | type MIME déclaré |
| `file` | flux PHP ouvert en lecture |
| `read(?int $length = null)` | lit jusqu'à `$length` octets (8192 par défaut) |
| `write(string $data)` | écrit dans le flux |
| `seek(int $offset, int $whence = SEEK_SET)` | déplace le curseur |
| `close()` | ferme le flux |

```php
<?php

$fichier->seek(0);                    // relire depuis le début
$contenu = $fichier->read();          // tout le fichier (par morceaux de 8 Kio)
$fichier->close();
```

!!! warning "Fermez le flux"
    `UploadFile` ouvre un flux. Pensez à `close()` si vous ne le consommez pas
    entièrement — PHP libère la ressource en fin de requête, mais le fermer tôt
    évite de garder un descripteur.

## Plusieurs fichiers

Un champ `input type="file" multiple` arrive comme l'entrée brute de `$_FILES`,
dont chaque clé contient un tableau :

```php
<?php

use Eorbahapi\Params\File;

$app->post('/documents', function (#[File] array $documents) {
    return ['noms' => $documents['name'] ?? []];
});
// noms: ['a.txt', 'b.txt']
```

L'entrée conserve la structure `$_FILES` (`name`, `type`, `tmp_name`, `error`,
`size`) ; chaque `tmp_name` est le chemin du fichier temporaire, à ouvrir avec
`fopen($documents['tmp_name'][$i], 'rb')`.

## Téléversement brut

Sans type `UploadFile`, l'entrée `$_FILES` est transmise telle quelle :

```php
<?php

use Eorbahapi\Params\File;

$app->post('/raw', function (#[File] array $fichier) {
    return ['taille' => $fichier['size'] ?? 0];
});
```

## Erreurs

- fichier absent et paramètre requis → **422** (`loc: ["file", "fichier"]`) ;
- échec de téléversement (`UPLOAD_ERR_*`) → **422** avec un message explicite ;
- à vous de valider le type MIME (`content_type`) et la taille maximale dans le
  handler ou avec un `FieldValidator`.

```php
<?php

use Eorbahapi\Exceptions\HTTPException;

$app->post('/upload', function (#[File] UploadFile $fichier) {
    if (!str_starts_with($fichier->content_type, 'image/')) {
        throw new HTTPException(415, 'Image attendue.');
    }

    return ['ok' => true];
});
```

## Récapitulatif

- `#[File] UploadFile $f` reçoit un fichier typé et son flux ;
- `read()`, `write()`, `seek()` et `close()` manipulent le flux ;
- un champ multiple arrive en tableau d'entrées ;
- `#[File] array` conserve l'entrée brute de `$_FILES` ;
- type MIME et taille se contrôlent dans le handler (ou un validateur).

[Suivant : En-têtes](headers.md){ .md-button }
