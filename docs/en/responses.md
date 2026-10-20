# Responses

## What you will learn

Choose what a handler returns — a JSON-encoded array, a typed response,
HTML, a redirect, a stream — and control the status, headers and
`Content-Type` with the helpers of `Eorbahapi\Response`.

## The code

`docs_src/ResponsesExample.php` :

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Responses\RedirectResponse;

/**
 * Réponses : statut, en-têtes, contenu, redirections.
 */
final class ResponsesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Un tableau est encodé en JSON automatiquement.
        $app->get('/auto', fn () => ['ok' => true]);

        // Contrôle explicite du statut et des en-têtes.
        $app->post('/created', fn () => new JSONResponse(['created' => true], 201, ['X-Trace' => 'abc']));

        // Réponse HTML.
        $app->get('/html', fn () => new HTMLResponse('<h1>Bonjour</h1>'));

        // Redirection typée.
        $app->get('/go', fn () => new RedirectResponse('/auto', 307));

        // Redirection raccourcie : "redirect:code:url".
        $app->get('/short', fn () => 'redirect:301:/auto');

        // Texte brut.
        $app->get('/text', fn () => 'bonjour');

        return $app;
    }
}
```

A handler does not have to return a `Response`: the framework normalizes
the return value. An `array` or an `object` becomes a JSON body, a string becomes a
text body, a string prefixed with `redirect:` becomes a redirect, and
`null` / `true` leave the current response unchanged.

## What happens

| Request | Status | Body / headers |
| --- | --- | --- |
| `GET /auto` | 200 | `{"ok":true}` |
| `POST /created` | 201 | `{"created":true}` + `X-Trace: abc` |
| `GET /html` | 200 | `<h1>Bonjour</h1>` + `Content-Type: text/html; charset=UTF-8` |
| `GET /go` | 307 | `Location: /auto` |
| `GET /short` | 301 | `Location: /auto` |
| `GET /text` | 200 | `bonjour` |

`JSONResponse` takes the data, a status (200 by default) and an array
of headers; it adds `Content-Type: application/json` unless you already
provide it (`$headers['Content-Type'] ??= 'application/json'`).
`HTMLResponse` does the same with `text/html; charset=UTF-8`.
`RedirectResponse` stores the URL in `Location` and sets the status (302 by
default, `307` here to preserve the method and the body).

The `redirect:code:url` string produces the same result without instantiating a class:
`'redirect:301:/auto'` splits on `:` into three parts, applies the status and
the `Location` header. The body, however, remains the string itself.

### Streaming responses

A `Generator` feeds `StreamingResponse` (`Eorbahapi\Responses\StreamingResponse`):
each `yield` is sent to the client and then flushed, which suits large contents
and progress updates. The signature is
`new StreamingResponse(\Generator $generator, int $status = 200, array $headers = [])`.

```php
$app->get('/chunks', fn () => new StreamingResponse((static function (): \Generator {
    yield 'a';
    yield 'b';
    yield 'c';
})()));
```

`EventStreamResponse` (`Eorbahapi\Responses\EventStreamResponse`) extends this
behavior for Server-Sent Events (SSE): it pre-fills
`Content-Type: text/event-stream`, `Cache-Control: no-cache` and
`Connection: keep-alive`. All that is left is to produce frames in SSE format
(`data: ...` followed by an empty line).

```php
$app->get('/events', fn () => new EventStreamResponse((static function (): \Generator {
    yield "data: ping\n\n";
})()));
```

These two responses have no body before sending: it is `send()` — called by
`run()` — that consumes the generator and pushes each chunk. Under `TestClient`,
`body()` therefore remains empty; a test checks the output, not the response.

### `Response` helpers

| Method | Role |
| --- | --- |
| `status()` | current HTTP status |
| `body()` | raw body (`string`, `array` or `null`) |
| `headers()` | array of headers |
| `text()` | body as a string, arrays being encoded as JSON |
| `set_content_type()` | `Content-Type` + `Cache-Control` in a single call |

`set_content_type('json')`, `'html'`, `'text'`, `'svg'`, `'png'`… accept a
short name translated into a MIME type, with `charset=UTF-8` added for textual
types. Without a cache age, the call sets `Cache-Control: no-store` and
`Pragma: no-cache`; with `set_content_type('pdf', 3600)`, it sets
`public, max-age=3600` and `Expires`. The `disposition`, `filename` and
`custom_headers` options complete the configuration.

!!! tip "The shortest path first"
    As long as you do not need a particular status or header,
    return an array: JSON encoding and the body are handled for you.
    Switch to `JSONResponse` as soon as a status (`201`) or a custom header may
    be useful to the client.

!!! warning "The shortened redirect does not carry a body"
    `'redirect:301:/auto'` is only used to redirect: the body is the redirect
    string. Use `new RedirectResponse($url, $status)` if you want
    a typed response, and reserve the `307` / `308` statuses for redirects
    that must preserve the request method and body.

## Summary

- an array or an object returned is encoded as JSON automatically;
- `JSONResponse($data, $status, $headers)` controls status and headers;
- `HTMLResponse` sets `text/html; charset=UTF-8`;
- `RedirectResponse($url, $status)` and `'redirect:code:url'` set `Location`;
- `StreamingResponse` sends a `Generator` chunk by chunk, `EventStreamResponse` sends it as SSE;
- `status()`, `body()`, `headers()`, `text()` and `set_content_type()` inspect and adjust the response.

[Next: Dependencies](dependencies.md){ .md-button }
