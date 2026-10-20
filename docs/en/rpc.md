# JSON-RPC

## What you will learn

Expose PHP functions as JSON-RPC 2.0 with `Eorbahapi\RPC\JsonRPC`:
register methods with `add_method()`, mount the server under a prefix,
understand how JSON parameters are resolved (name, type, default value),
validate `params` with a Svinx model, read the response format —
success, `-32600`, `-32601`, `-32602`, `-32603` — and
know why the HTTP status stays `200` even when the response is an error.

## The code

`docs_src/RpcExample.php` declares a model describing the inputs, then mounts a
JSON-RPC server under `/rpc`:

```php
<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\RPC\JsonRPC;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Paramètres de la méthode JSON-RPC.
 */
final class RpcSumParams extends BaseModel
{
    #[Field(ge: 0)]
    public int $a = 0;

    #[Field(ge: 0)]
    public int $b = 0;
}

/**
 * Serveur JSON-RPC 2.0 monté sous /rpc.
 */
final class RpcExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $rpc = new JsonRPC();

        // Une méthode JSON-RPC est un callable dont les paramètres sont résolus
        // comme ceux d'une route (Params, Depends, modèles Svinx...).
        $rpc->add_method('math.sum', fn (int $a, int $b) => $a + $b);
        $rpc->add_method('greet', fn (string $name = 'monde') => 'Bonjour ' . $name);

        $app = new EorbahAPI();
        $app->mount('/rpc', $rpc);

        return $app;
    }
}
```

`JsonRPC` is a mounted application, like `StaticFiles`: `mount('/rpc', $rpc)`
injects the shared request and response into it, `process()` reads the request
body, picks the method, calls the callable and fills the response. Nothing is
sent: the pipeline decides.

`add_method(string $method, callable $callback)` associates a public name (`math.sum`,
`greet`) with a callable, with exactly the parameters you want. Values are looked
up **by name** in `params`:

| Case | Result |
| --- | --- |
| `params: ['a' => 2, 'b' => 3]` | `2` and `3` passed to the method |
| `params: ['n' => '7']` for `int $n` | coercion to `7` |
| parameter missing with a default value | the default value (`'monde'`) |
| parameter missing without a default | error `-32603` (`Impossible de résoudre le paramètre …`) |
| key present in `params` but not in the signature | ignored |
| parameter typed `Request` / `Response` | injected automatically |
| `#[Body] RpcSumParams $params` | `params` is validated by the Svinx model |
| validation failure | error `-32602` (`Invalid params: …`) |

The envelope's `params` become the **body** of the request passed to the
resolver (`Request::withBody()`): parameter attributes therefore behave
as on a route — `#[Body] RpcSumParams $params` validates `params` with the
constraints of `Svinx\Validator`, and `Request::body()` exposes the same values.
Only `#[Query]` keeps reading the HTTP query string, not `params`.

## What happens

```php
public static function scenarios(): array
{
    return [
        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.sum',
            'params' => ['a' => 2, 'b' => 3],
            'id' => 1,
        ]], json: ['jsonrpc' => '2.0', 'result' => 5, 'id' => 1]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'greet',
            'params' => ['name' => 'Oumar'],
            'id' => 2,
        ]], json: ['jsonrpc' => '2.0', 'result' => 'Bonjour Oumar', 'id' => 2]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.validated',
            'params' => ['a' => 4, 'b' => 6],
            'id' => 4,
        ]], json: ['jsonrpc' => '2.0', 'result' => 10, 'id' => 4]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'math.validated',
            'params' => ['a' => -1],
            'id' => 5,
        ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32602, 'message' => "Invalid params: Le champ 'a' doit être >= 0"], 'id' => 5]),

        self::scenario('POST', '/rpc', ['json' => [
            'jsonrpc' => '2.0',
            'method' => 'inconnue',
            'id' => 3,
        ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32601, 'message' => 'Method not found'], 'id' => 3]),
    ];
}
```

| Request | Status | Body |
| --- | --- | --- |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.sum","params":{"a":2,"b":3},"id":1}` | 200 | `{"jsonrpc":"2.0","result":5,"id":1}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"greet","params":{"name":"Oumar"},"id":2}` | 200 | `{"jsonrpc":"2.0","result":"Bonjour Oumar","id":2}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.validated","params":{"a":4,"b":6},"id":4}` | 200 | `{"jsonrpc":"2.0","result":10,"id":4}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"math.validated","params":{"a":-1},"id":5}` | 200 | `{"jsonrpc":"2.0","error":{"code":-32602,"message":"Invalid params: Le champ 'a' doit être >= 0"},"id":5}` |
| `POST /rpc` · `{"jsonrpc":"2.0","method":"inconnue","id":3}` | 200 | `{"jsonrpc":"2.0","error":{"code":-32601,"message":"Method not found"},"id":3}` |

The response body always contains `jsonrpc`, `id` and either `result` or
`error`. The error codes produced by the server:

| Code | Message | Trigger |
| --- | --- | --- |
| `-32600` | `Invalid Request` | body without `method`, or `jsonrpc` different from `"2.0"`, or array (batches are not supported), or request without a JSON body |
| `-32601` | `Method not found` | method name absent from `add_method()` |
| `-32602` | `Invalid params: …` | `params` rejected by Svinx validation (`#[Body]` on a model) |
| `-32603` | `Internal error: …` | exception during parameter resolution or inside the method |

The error's `id` echoes the request's; it is `null` when the request
cannot be processed — including when no JSON request was sent (a
`GET /rpc`).

!!! warning "The HTTP status is `200`, even for an error"
    JSON-RPC 2.0 separates transport and protocol: the call was indeed
    processed, it is the method that failed. The client must therefore read the
    `error` field of the body, not just rely on the HTTP code. An exception raised in
    your method becomes a `-32603` whose message starts with
    `Internal error: ` followed by the original message — be careful not to leak
    internal details in production. Validation errors, on the other hand,
    are `-32602` that echo the first Svinx message.

!!! tip "Mounting and prefix"
    The mount prefix selects the server; the path under that
    prefix is ignored, as are the URL parameters. A server mounted
    under `/api/rpc` responds the same way on `/api/rpc` and `/api/rpc/x`.
    `params` become the request body for the resolver: `#[Body]`
    and `Request::body()` see them. `#[Query]`, however, always reads the HTTP
    query string.

## Summary

- `new JsonRPC()` then `$rpc->add_method($name, $callable)`, and
  `$app->mount('/rpc', $rpc)` to expose it;
- parameters come from `params` by name, with type coercion, default
  value and injection of `Request`/`Response`;
- `params` also serves as the body (`Request::withBody()`), so `#[Body]` validates a
  Svinx model;
- success: `{"jsonrpc":"2.0","result":…,"id":…}`;
- error: `{"jsonrpc":"2.0","error":{"code":…,"message":…},"id":…}`;
- `-32600` invalid request, `-32601` unknown method, `-32602` invalid
  parameters, `-32603` internal error;
- the HTTP status stays `200`: the body carries the result or the error.

[Next: Testing](testing.md){ .md-button }
