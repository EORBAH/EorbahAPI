# Twig Templates

## What you will learn

Render HTML with Twig from an EorbahAPI route: create an
`Eorbahapi\Templating\TwigTemplates` engine, make it injectable by the container,
render a `.twig` file with `TemplateResponse()`, render a template string on the
fly with `createTemplate()`, and understand what the framework does — or does not
do — with the `Content-Type` of an HTML response.

## The code

`docs_src/TemplatingExample.php` prepares a templates directory in the temporary
folder, then exposes it to the application:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Templating\TwigTemplates;

final class TemplatingExample extends DocsExample
{
    public static function templateDir(): string
    {
        $dir = sys_get_temp_dir() . '/eorbah_docs_templates';
        $cache = sys_get_temp_dir() . '/eorbah_docs_templates_cache';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }

        file_put_contents(
            $dir . '/index.html.twig',
            '<h1>{{ title }}</h1><ul>{% for item in items %}<li>{{ item }}</li>{% endfor %}</ul>'
        );

        return $dir;
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $templates = new TwigTemplates(
            self::templateDir(),
            sys_get_temp_dir() . '/eorbah_docs_templates_cache'
        );

        $app->register(TwigTemplates::class, $templates);

        // Le moteur de templates est injecté par son type.
        $app->get('/', function (TwigTemplates $twig) {
            return $twig->TemplateResponse('index.html.twig', [
                'title' => 'EorbahAPI',
                'items' => ['routes', 'params', 'tests'],
            ]);
        });

        // Rendu d'un template à la volée.
        $app->get('/inline', function (TwigTemplates $twig) {
            return $twig->createTemplate('Bonjour {{ name }}', ['name' => 'Oumar']);
        });

        return $app;
    }
}
```

The constructor is `new TwigTemplates($templateDir, $cacheDir, $debug)`:
`$templateDir` defaults to `'templates'`, the cache is computed from the current
directory (`cache/twig`) if you omit `$cacheDir`, and `$debug` (`false`) enables
Twig's debugging information. `auto_reload` stays active: a modified template is
reloaded on the next request.

Both rendering methods return an **HTML string**:

| Method | What it renders |
| --- | --- |
| `TemplateResponse(string $template, array $data = [])` | a file from the templates directory (`index.html.twig`) |
| `createTemplate(string $content, array $data = [])` | a template written in the string (`'Bonjour {{ name }}'`) |

## What happens

```php
public static function scenarios(): array
{
    return [
        self::scenario('GET', '/', contains: '<h1>EorbahAPI</h1><ul><li>routes</li><li>params</li><li>tests</li></ul>'),
        self::scenario('GET', '/inline', contains: 'Bonjour Oumar'),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `GET /` | 200 | `<h1>EorbahAPI</h1><ul><li>routes</li><li>params</li><li>tests</li></ul>` |
| `GET /inline` | 200 | `Bonjour Oumar` |

In both cases, the handler returns a string: the framework places it in the
response body, keeps the `200` status and **does not force any
`Content-Type`** — the response header list stays empty. Twig escapes the
variables (`{{ title }}`) according to its default HTML `autoescape`, but it is
not the framework that decides the MIME type.

To announce it explicitly, return an `Eorbahapi\Responses\HTMLResponse`
(it sets `Content-Type: text/html; charset=UTF-8`, unless you pass
`setContentType: false`):

```php
use Eorbahapi\Responses\HTMLResponse;

$app->get('/page', function (TwigTemplates $twig) {
    $html = $twig->TemplateResponse('index.html.twig', ['title' => 'EorbahAPI', 'items' => []]);

    return new HTMLResponse($html);
});
```

!!! tip "`register()` sets the configuration, type injection carries it"
    `$app->register(TwigTemplates::class, $templates)` associates your instance
    with the dependency container: any parameter typed `TwigTemplates $twig`
    receives exactly this engine, with its directory and its cache. This is what
    makes rendering available in several routes without a global variable.
    Remember to create the cache directory: Twig writes into it.

!!! warning "A returned string is not a typed HTML response"
    Returning `$twig->TemplateResponse(...)` gives a `200` with an HTML body
    but without `Content-Type`. A browser copes with it, a strict client or a
    test that checks the header does not. Use `HTMLResponse` (or your own
    `Response` with the header) as soon as the MIME type matters.

## Summary

- `new TwigTemplates($templateDir, $cacheDir, $debug)` wraps a
  `Twig\Environment` over a directory of `.twig` files;
- `$app->register(TwigTemplates::class, $templates)` makes the engine injectable
  into handlers by simple typing;
- `TemplateResponse($template, $data)` renders a file,
  `createTemplate($content, $data)` renders a string;
- both return a string: status `200`, `Content-Type` not forced;
- `HTMLResponse` sets `Content-Type: text/html; charset=UTF-8`;
- Twig escapes variables and reloads modified templates.

[Next: Error handling](errors.md){ .md-button }
