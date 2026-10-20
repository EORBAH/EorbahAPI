# Files

## What you will learn

Receive uploads (`multipart/form-data`) with `#[File]`: get a typed `UploadFile` object that can be read as a stream, or the raw `$_FILES` entry, and understand how an upload failure becomes a `422`.

## The code

`docs_src/FilesExample.php`:

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

`#[File]` comes from `Eorbahapi\Params\File` and reads `$_FILES` (via `Request::file()`). The field name on the wire is the name of the PHP parameter; `#[File('document')]` renames it.

Here again, the behavior depends on the **declared type**:

| Declared type | What the handler receives |
| --- | --- |
| `Eorbahapi\Datastructures\UploadFile` | an object, with the stream already open for reading |
| `array` (or anything else) | the raw entry: `name`, `type`, `tmp_name`, `error`, `size` |

`UploadFile` is deliberately minimal:

| Member | Role |
| --- | --- |
| `$filename` | original name sent by the client |
| `$content_type` | MIME type declared by the client |
| `read($length = null)` | reads `$length` bytes (8192 by default) |
| `write($data)` | writes to the stream |
| `seek($offset, $whence = SEEK_SET)` | repositions the cursor |
| `close()` | closes the stream |

So you read the file **as a stream**: no need to load a large file into memory, `read(64)` is happy with the first 64 bytes. Since the stream is seekable, `seek(0)` lets you read again from the beginning after a first `read()`.

## What happens

| Request | Status | Body |
| --- | --- | --- |
| `POST /upload` · file `avatar` (`photo.png`, `image/png`) | 200 | `{"filename":"photo.png","content_type":"image/png","preview":"<?php"}` |
| `POST /meta` · file `document` (`rapport.pdf`) | 200 | `{"name":"rapport.pdf","size":1024}` |
| `POST /upload` · no file | 422 | `{"error":true,…,"details":[{"loc":["file","avatar"],"type":"missing"}]}` |

The `preview` is `<?php` because the test scenario uploads the example's own file: the first five bytes are those of the PHP header. That is a demonstration shortcut — the framework itself simply reads what the client sends.

!!! warning "An `UPLOAD_ERR_*` becomes a 422 of type `file`"
    Before opening the stream, the resolver checks `error` in `$_FILES`.
    `UPLOAD_ERR_INI_SIZE`, `UPLOAD_ERR_FORM_SIZE`, `UPLOAD_ERR_PARTIAL`… are
    converted into a `422` with `"type": "file"` and a message such as
    `Le fichier 'avatar' n'a pas été téléversé correctement (code 1).` An
    unreadable `tmp_name` yields the same family of error:
    `Le fichier 'avatar' est illisible.` The handler is never called.

!!! note "The raw array checks nothing"
    With `#[File('document')] array $document`, the `$_FILES` entry is
    passed through as is: `error` is not checked. That is handy for a
    multiple upload (the array of entries is preserved), but it is up to you
    to test `$document['error']` and reject files in error.

## Summary

- `#[File]` (`Eorbahapi\Params\File`) reads `$_FILES`, in `multipart/form-data`;
- `#[File] UploadFile $avatar` builds an object with `$filename`,
  `$content_type` and an open stream;
- `read()`, `write()`, `seek()`, `close()` work on the stream, without loading
  everything into memory;
- `#[File('document')] array $document` gives the raw `$_FILES` entry;
- an `UPLOAD_ERR_*` code produces a `422` with `loc = [file, …]` and
  `type: file`;
- `#[File]` extends `#[Form]`, which extends `#[Body]`: a single handler can
  combine form fields and files.

[Next: Headers](headers.md){ .md-button }
