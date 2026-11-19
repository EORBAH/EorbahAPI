# Fichiers statiques

## Ce que vous allez apprendre

Servir un répertoire de fichiers (CSS, JS, images, PDF) avec les bons en-têtes,
la compression et les protections de base.

## Monter un répertoire

`StaticFiles` est une application montée : elle reçoit le chemin relatif au
préfixe et remplit la réponse partagée.

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;

$app = new EorbahAPI();

$app->mount('/static', new StaticFiles(__DIR__ . '/public'));

$app->run();
```

```console
$ curl -i "http://127.0.0.1:8000/static/style.css"
HTTP/1.1 200 OK
Content-Type: text/css
Cache-Control: no-cache, no-store, must-revalidate
```

Un chemin inexistant répond `404 File not found`.

## Options

```php
<?php

$app->mount('/assets', new StaticFiles(__DIR__ . '/public', [
    'index' => 'index.html',
    'cache_control' => 'public, max-age=3600',
    'compression' => true,
    'allowed_extensions' => ['css', 'js', 'png', 'svg', 'woff2'],
]));
```

| Option | Défaut | Rôle |
| --- | --- | --- |
| `index` | `index.html` | fichier servi pour un répertoire |
| `cache_control` | `no-cache, no-store, must-revalidate` | valeur de `Cache-Control` |
| `compression` | `true` | gzip si le client l'accepte |
| `allowed_extensions` | liste étendue | extensions autorisées |

Le service calcule aussi `Last-Modified` et `ETag` (requêtes conditionnelles
`If-None-Match` / `If-Modified-Since` → `304`).

## Sécurité

- les chemins sont normalisés : `../` ne peut pas sortir du répertoire ;
- les extensions non autorisées ne sont pas servies ;
- le répertoire est résolu avec `realpath()` à la construction : un dossier
  inexistant lève une `EorbahAPIError`.

!!! tip "Service testable"
    Comme toute application montée, `StaticFiles` passe par `handle()` : vous
    pouvez tester un fichier servi avec `TestClient`, sans serveur HTTP.

```php
<?php

use Eorbahapi\Testing\TestClient;

$response = (new TestClient($app))->get('/static/style.css');
$response->status();                    // 200
$response->header('Content-Type');      // text/css
```

## Récapitulatif

- `new StaticFiles($dir, $options)` sert un répertoire ;
- `$app->mount('/static', ...)` l'expose sous un préfixe ;
- `index`, `cache_control`, `compression` et `allowed_extensions` se règlent
  par options ;
- ETag/Last-Modified/304 et compression sont gérés ;
- les chemins sont protégés contre la traversée de répertoire.

[Suivant : Templates Twig](templates.md){ .md-button }
