# Fichiers statiques

## Ce que vous allez apprendre

Servir un répertoire de fichiers depuis une application EorbahAPI : monter
`Eorbahapi\StaticFiles` sous un préfixe, régler ses options (`index`,
`cache_control`, `compression`, `allowed_extensions`), comprendre les en-têtes
qu'il pose (`Content-Type`, `Last-Modified`, `ETag`, `Cache-Control`), la
compression gzip et les réponses `304 Not Modified`, et savoir ce qui empêche
de sortir du répertoire.

## Le code

`docs_src/StaticFilesExample.php` fabrique un petit répertoire public dans le
dossier temporaire, puis le monte sous `/static` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;

final class StaticFilesExample extends DocsExample
{
    public static function publicDir(): string
    {
        $dir = sys_get_temp_dir() . '/eorbah_docs_static';

        if (!is_dir($dir . '/assets')) {
            mkdir($dir . '/assets', 0777, true);
        }

        file_put_contents($dir . '/index.html', '<h1>Documentation</h1>');
        file_put_contents($dir . '/assets/style.css', 'body{margin:0}');

        return $dir;
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Le préfixe /static est retiré avant de chercher le fichier.
        $app->mount('/static', new StaticFiles(self::publicDir()));

        $app->get('/api', fn () => ['api' => true]);

        return $app;
    }
}
```

`StaticFiles` étend `BaseMountedApplication` : `mount()` lui injecte la requête
et la réponse partagées, `process()` remplit cette réponse, et `handle()` la
retourne **sans l'envoyer**. Le service est donc utilisable derrière un serveur
web comme depuis un `TestClient`.

Les options du constructeur `new StaticFiles($directory, $options)` :

| Option | Valeur par défaut | Effet |
| --- | --- | --- |
| `index` | `'index.html'` | fichier servi quand le chemin demandé est un répertoire |
| `cache_control` | `'no-cache, no-store, must-revalidate'` | valeur de `Cache-Control` ; une chaîne vide n'envoie pas l'en-tête |
| `compression` | `true` | compresse le corps quand le client accepte gzip |
| `allowed_extensions` | `html`, `css`, `js`, `jsx`, `json`, `xml`, `png`, `jpg`, `jpeg`, `gif`, `ico`, `svg`, `ttf`, `woff`, `woff2`, `pdf`, `txt`, `md`, `webmanifest`, `tx`, `eot`, `xcss` | extensions servies ; tout le reste répond `404` |

## Ce qui se passe

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/static/index.html', contains: '<h1>Documentation</h1>', headers: ['Content-Type' => 'text/html']),
        self::scenario('GET', '/static', contains: '<h1>Documentation</h1>'),
        self::scenario('GET', '/static/assets/style.css', contains: 'body{margin:0}', headers: ['Content-Type' => 'text/css']),
        self::scenario('GET', '/static/absent.css', status: 404, contains: 'File not found'),
        self::scenario('GET', '/api', json: ['api' => true]),
    ];
}
```

| Requête | Statut | Corps |
| --- | --- | --- |
| `GET /static/index.html` | 200 | `<h1>Documentation</h1>` |
| `GET /static` | 200 | `<h1>Documentation</h1>` |
| `GET /static/assets/style.css` | 200 | `body{margin:0}` |
| `GET /static/absent.css` | 404 | `File not found` |
| `GET /api` | 200 | `{"api":true}` |

Le préfixe monté est retiré avant la recherche : `/static/index.html` devient
`/index.html`, et `/static` désigne la racine du répertoire, dont le fichier
`index` est servi. Les en-têtes posés pour un fichier trouvé :

| En-tête | Valeur |
| --- | --- |
| `Content-Type` | déduit de l'extension (`text/html`, `text/css`, `application/javascript`, `image/svg+xml`…), sinon `mime_content_type()`, sinon `application/octet-stream` |
| `Last-Modified` | date GMT de la dernière modification du fichier |
| `ETag` | `md5(chemin + mtime + taille)`, entre guillemets doubles |
| `Cache-Control` | l'option `cache_control` |
| `Content-Length` | taille du corps envoyé (donc de la version compressée le cas échéant) |
| `Content-Encoding` / `Vary` | ajoutés **uniquement** si le corps est réellement compressé |

Deux requêtes conditionnelles évitent de retransmettre le fichier : si
`If-None-Match` correspond à l'`ETag`, ou si `If-Modified-Since` atteint la date
du fichier, la réponse est `304` avec un corps vide — les en-têtes de
cache restent présents, `Content-Length` disparaît. La compression, elle, ne
s'applique que si le client envoie un `Accept-Encoding` contenant `gzip` (ou `br`
si l'extension brotli est présente) **et** si le résultat est plus petit que
l'original : un fichier de 22 octets n'est pas compressé, un CSS de 5 000 octets
tombe à quelques dizaines d'octets avec `Content-Encoding: gzip` et
`Vary: Accept-Encoding`.

!!! tip "Choisissez `cache_control` selon l'environnement"
    Le défaut `no-cache, no-store, must-revalidate` est parfait en
    développement : vous modifiez un CSS, vous rechargez, vous le voyez. En
    production, montez plutôt `cache_control: 'public, max-age=3600'` et laissez
    l'`ETag` gérer la revalidation — le client renverra `If-None-Match` et
    recevra `304` au lieu du fichier.

!!! warning "Ce n'est pas un serveur de fichiers généraliste"
    Une requête hors des extensions autorisées ou hors du répertoire répond
    `404 File not found`, jamais autre chose : le chemin est nettoyé (`../` et
    `..\` retirés, séparateurs normalisés) puis vérifié avec `realpath()`, qui
    doit commencer par le répertoire de base. Le service ne regarde que le
    chemin, pas la méthode HTTP (un `POST` sur un fichier reçoit le fichier), et
    les requêtes `Range` ne sont pas gérées.

## Récapitulatif

- `new StaticFiles($directory, $options)` s'utilise monté : `$app->mount('/static', new StaticFiles($dir))` ;
- le préfixe monté est retiré de l'URI avant de chercher le fichier ;
- options : `index` (`index.html`), `cache_control`, `compression` (`true`), `allowed_extensions` ;
- un fichier trouvé pose `Content-Type`, `Last-Modified`, `ETag`, `Cache-Control` et `Content-Length` ;
- `Content-Encoding: gzip` et `Vary: Accept-Encoding` n'apparaissent que si le corps rétrécit vraiment ;
- `If-None-Match` / `If-Modified-Since` donnent un `304` sans corps ;
- extension non autorisée, chemin hors répertoire ou fichier absent : `404 File not found`.

[Suivant : Templates Twig](templates.md){ .md-button }
