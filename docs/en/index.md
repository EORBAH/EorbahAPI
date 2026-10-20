# EorbahAPI

**Modern, fast, typed PHP web framework for building APIs.**

EorbahAPI brings together three simple ideas:

- **declarative routes** — `#[Get]`, `#[Post]`, `#[Query]`, `#[Body]`… attributes or callbacks;
- **typed and validated parameters** — validation is provided by [`Svinx\Validator`](https://github.com/svinx-org/validator), inspired by Pydantic (`ge`, `le`, `min_length`, `pattern`, `choices` constraints, nested models, serialization);
- **a testable pipeline** — `EorbahAPI::handle(Request): Response` builds the response without sending it, which lets you plug in a web server, a CLI runner, a queue worker… or the `TestClient` of the test suite.

```php
<?php

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\{Path, Query};

$app = new EorbahAPI();

$app->get('/items/{id}', function (
    #[Path(ge: 1)] int $id,
    #[Query(ge: 1, le: 100)] int $limit = 10,
) {
    return ['id' => $id, 'limit' => $limit];
});

$app->run();
```

```console
$ curl "http://localhost:8000/items/7?limit=3"
{"id":7,"limit":3}
```

## A course, not a list of functions

This site is a **progressive course**, in the spirit of the FastAPI documentation. Each chapter follows the same flow:

1. what you will learn;
2. the example code;
3. what happens, request by request;
4. a summary.

Each chapter is based on a real file from `docs_src/`. These files are **executed by the test suite** (`tests/ExamplesTest.php`): if an example in the course no longer works, the tests fail. The documentation cannot lie.


## Where to start?

| If you want to… | Read |
| --- | --- |
| install the framework | [Installation](installation.md) |
| write your first API | [First steps](first-steps.md) |
| understand routing | [Routing](routing.md) |
| validate parameters | [Query parameters](query-params.md) and [Validations](validations.md) |
| receive JSON | [Request body](request-body.md) |
| secure a route | [Security](security.md) |
| test your application | [Testing](testing.md) |
| run the application other than over HTTP | [Custom runners](runners.md) |

## Full table of contents

**Getting started**

- [Installation](installation.md)
- [First steps](first-steps.md)

**Course guide**

- [Routing](routing.md)
- [Path parameters](path-params.md)
- [Query parameters](query-params.md)
- [Validations](validations.md)
- [Request body](request-body.md)
- [Nested models](nested-models.md)
- [Forms](forms.md)
- [Files](files.md)
- [Headers](headers.md)
- [Cookies](cookies.md)
- [Responses](responses.md)

**Going further**

- [Dependencies](dependencies.md)
- [Security](security.md)
- [Middlewares](middlewares.md)
- [Controllers](controllers.md)
- [Static files](static-files.md)
- [Twig templates](templates.md)
- [Error handling](errors.md)
- [JSON-RPC](rpc.md)
- [Testing](testing.md)
- [Custom runners](runners.md)

**Reference**

- [API reference](reference.md)

## Requirements

- PHP **8.1+** (the course uses PHP 8.4);
- Composer;
- the `mbstring` and `json` extensions;
- for testing: PHPUnit.

!!! tip "Course convention"
    The examples use anonymous functions to keep things short. The
    [Controllers](controllers.md) and [Middlewares](middlewares.md) chapters
    show the "class + attributes" form, suited to projects that grow.
