# UploadFile

`Eorbahapi\Datastructures\UploadFile` représente un fichier uploadé et expose un petit ensemble d'outils pour lire/écrire et fermer le flux.

## Exemple

```php
use Eorbahapi\Datastructures\UploadFile;

$handle = fopen('php://temp', 'w+');
$file = new UploadFile('report.txt', 'text/plain', $handle);

$file->write("hello\n");
$file->seek(0);
echo $file->read();
$file->close();
```

## Méthodes

- `write($data)`
- `read($length = null)`
- `seek($offset, $whence = SEEK_SET)`
- `close()`

Cette classe est utile quand vous voulez manipuler le flux d'un fichier téléchargé sans dépendre directement de `$_FILES` dans votre logique métier.
