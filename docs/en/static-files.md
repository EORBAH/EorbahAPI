# Static files

## What you will learn

Serve a directory of files from an EorbahAPI application: mount
`Eorbahapi\StaticFiles` under a prefix, tune its options (`index`,
`cache_control`, `compression`, `allowed_extensions`), understand the headers
it sets (`Content-Type`, `Last-Modified`, `ETag`, `Cache-Control`), gzip
compression and `304 Not Modified` responses, and know what prevents escaping
the directory.

## The code

`docs_src/StaticFilesExample.php` builds a small public directory in the
temporary folder, then mounts it under `/static`:

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

`StaticFiles` extends `BaseMountedApplication`: `mount()` injects the shared
request and response into it, `process()` fills that response, and `handle()`
returns it **without sending it**. The service is therefore usable behind a web
server as well as from a `TestClient`.

The options of the `new StaticFiles($directory, $options)` constructor:

| Option | Default value | Effect |
| --- | --- | --- |
| `index` | `'index.html'` | file served when the requested path is a directory |
| `cache_control` | `'no-cache, no-store, must-revalidate'` | value of `Cache-Control`; an empty string does not send the header |
| `compression` | `true` | compresses the body when the client accepts gzip |
| `allowed_extensions` | `html`, `css`, `js`, `jsx`, `json`, `xml`, `png`, `jpg`, `jpeg`, `gif`, `ico`, `svg`, `ttf`, `woff`, `woff2`, `pdf`, `txt`, `md`, `webmanifest`, `tx`, `eot`, `xcss` | extensions served; everything else responds `404` |

## What happens

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

| Request | Status | Body |
| --- | --- | --- |
| `GET /static/index.html` | 200 | `<h1>Documentation</h1>` |
| `GET /static` | 200 | `<h1>Documentation</h1>` |
| `GET /static/assets/style.css` | 200 | `body{margin:0}` |
| `GET /static/absent.css` | 404 | `File not found` |
| `GET /api` | 200 | `{"api":true}` |

The mounted prefix is stripped before the lookup: `/static/index.html` becomes
`/index.html`, and `/static` refers to the root of the directory, whose `index`
file is served. The headers set for a found file:

| Header | Value |
| --- | --- |
| `Content-Type` | inferred from the extension (`text/html`, `text/css`, `application/javascript`, `image/svg+xml`…), otherwise `mime_content_type()`, otherwise `application/octet-stream` |
| `Last-Modified` | GMT date of the file's last modification |
| `ETag` | `md5(path + mtime + size)`, in double quotes |
| `Cache-Control` | the `cache_control` option |
| `Content-Length` | size of the body sent (so of the compressed version where applicable) |
| `Content-Encoding` / `Vary` | added **only** if the body is actually compressed |

Two conditional requests avoid retransmitting the file: if `If-None-Match`
matches the `ETag`, or if `If-Modified-Since` reaches the file's date, the
response is `304` with an empty body — the cache headers remain present,
`Content-Length` disappears. Compression, for its part, only applies if the
client sends an `Accept-Encoding` containing `gzip` (or `br` if the brotli
extension is present) **and** if the result is smaller than the original: a
22-byte file is not compressed, a 5,000-byte CSS drops to a few dozen bytes with
`Content-Encoding: gzip` and `Vary: Accept-Encoding`.

!!! tip "Choose `cache_control` according to the environment"
    The default `no-cache, no-store, must-revalidate` is perfect in
    development: you change a CSS file, you reload, you see it. In
    production, mount `cache_control: 'public, max-age=3600'` instead and let
    the `ETag` handle revalidation — the client will send `If-None-Match` and
    receive `304` instead of the file.

!!! warning "This is not a general-purpose file server"
    A request outside the allowed extensions or outside the directory responds
    `404 File not found`, never anything else: the path is cleaned (`../` and
    `..\` removed, separators normalized) then checked with `realpath()`, which
    must start with the base directory. The service only looks at the path, not
    the HTTP method (a `POST` on a file receives the file), and `Range`
    requests are not handled.

## Summary

- `new StaticFiles($directory, $options)` is used mounted: `$app->mount('/static', new StaticFiles($dir))`;
- the mounted prefix is stripped from the URI before looking up the file;
- options: `index` (`index.html`), `cache_control`, `compression` (`true`), `allowed_extensions`;
- a found file sets `Content-Type`, `Last-Modified`, `ETag`, `Cache-Control` and `Content-Length`;
- `Content-Encoding: gzip` and `Vary: Accept-Encoding` only appear if the body really shrinks;
- `If-None-Match` / `If-Modified-Since` yield a `304` with no body;
- disallowed extension, path outside the directory or missing file: `404 File not found`.

[Next: Twig Templates](templates.md){ .md-button }
