<div align="center">
  <h1>EorbahAPI</h1>
  <p>PHP web framework for building APIs: declarative routes, typed parameters validated by <code>Svinx\Validator</code>, testable HTTP pipeline.</p>
  <img src="https://img.shields.io/badge/license/MIT%2B-green" alt="License" />
</div>

<br>

**EorbahAPI** brings together three ideas: declarative routes (`#[Get]`,
`#[Query]`, `#[Body]`…), Pydantic-style validation (through
[Svinx\Validator](https://github.com/svinx-org/validator)) and a
`handle(Request): Response` entry point that builds the response without sending
it — so it is testable and reusable by any runner (CLI, worker, another app).

## Quick start

```bash
composer require eor_bah545/eorbahapi
```

```php
<?php
// public/index.php

require __DIR__ . '/../vendor/autoload.php';

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\{Path, Query};

$app = new EorbahAPI(debug: true);

$app->get('/items/{id}', function (
    #[Path(ge: 1)] int $id,
    #[Query(ge: 1, le: 100)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

```console
$ php -S localhost:8000 -t public
$ curl "http://localhost:8000/items/7?limit=3"
{"id":7,"limit":3}
```

## What the framework provides

| Area | Element |
| --- | --- |
| Routing | `get/post/put/patch/delete/head/options`, `add_api_route`, `{id}` and `{path:path}` routes |
| Parameters | `#[Path]`, `#[Query]`, `#[Header]`, `#[Cookie]`, `#[Body]`, `#[Form]`, `#[File]`, `#[Security]`, `#[Depends]` |
| Validation | Svinx constraints (`ge`, `le`, `min_length`, `pattern`, `choices`…), `BaseModel`, detailed 422 errors |
| Responses | `JSONResponse`, `HTMLResponse`, `RedirectResponse`, `StreamingResponse`, `EventStreamResponse`, `redirect:code:url` string |
| Middlewares | global, per-route, `#[Middleware(Class::class, args: [...])]`, CORS, session, trusted hosts, rate limiting |
| Security | `HTTPBearer`, `HTTPBasic`, API keys, OAuth2/OIDC, JWT |
| Mounted apps | `StaticFiles`, `JsonRPC` 2.0, nested `EorbahAPI` applications |
| Testing | `TestClient` / `TestResponse` — no server, no socket |
| Controllers | `#[Route]`, `#[Get]`… + `$app->include_controller(new X(), prefix: '/api')` |

## Documentation

The full course (25 chapters) lives in [`docs/fr`](docs/fr/index.md) (French is
the source language; translations are generated with `scripts/translate.py`):

- getting started: [Installation](docs/fr/installation.md) · [First steps](docs/fr/first-steps.md);
- core: [Routing](docs/fr/routing.md) · [Parameters](docs/fr/query-params.md) · [Validations](docs/fr/validations.md) · [Request body](docs/fr/request-body.md);
- going further: [Security](docs/fr/security.md) · [Middlewares](docs/fr/middlewares.md) · [Testing](docs/fr/testing.md) · [Runners](docs/fr/runners.md);
- reference: [API reference](docs/fr/reference.md).

Every chapter is backed by a file in [`docs_src/`](docs_src) **executed by the
test suite**: the documentation cannot drift away from the code.

The site is built with **MkDocs** (Material theme):

```bash
python scripts/build_docs.py --install   # once: creates .venv-docs/ and installs MkDocs
./scripts/docs_start.sh                  # http://localhost:4000 (hot reload)
python scripts/build_docs.py             # builds build/ (published to GitHub Pages)
```

## Validation

```php
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

class Product extends BaseModel
{
    #[Field(min_length: 2, max_length: 50)]
    public string $name;

    #[Field(gt: 0)]
    public float $price;
}

$app->post('/products', fn (#[Body] Product $product) => $product->model_dump());
```

An invalid value produces a `422` response:

```json
{
  "error": true,
  "status": 422,
  "message": "Request validation error",
  "details": [{"loc": ["query", "limit"], "type": "value_error", "msg": "Le paramètre query 'limit' doit être >= 1"}]
}
```

## Tests

```bash
composer test                                # full suite (177 tests)
./vendor/bin/phpunit --filter ExamplesTest   # checks the documentation examples
```

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Testing\TestClient;

$app = new EorbahAPI();
$app->get('/ping', fn () => ['pong' => true]);

$response = (new TestClient($app))->get('/ping');

$response->assertStatus(200)->json();   // ['pong' => true]
```

## Developing with an agent

The project map for coding assistants is in
[`.agents/skills/eorbahapi`](.agents/skills/eorbahapi/SKILL.md): architecture,
request pipeline, invariants, recipes, testing and documentation conventions.

## License

Released under the MIT license. See [LICENSE](LICENSE) and
[CONTRIBUTING.md](CONTRIBUTING.md).
