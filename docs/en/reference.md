# API Reference

This page summarizes the framework's public surface. Each entry points to the course chapter that details it.

## `Eorbahapi\EorbahAPI`

| Member | Role |
| --- | --- |
| `__construct(?string $title, ?string $description, bool $debug = false, ?string $prefix = '')` | Creates the application. |
| `run(?Request $request = null): void` | Processes the request (real or provided) and **sends** the response. |
| `handle(Request $request, ?Response $response = null): Response` | Processes the request and **returns** the response, without sending it. |
| `get/post/put/patch/delete/head/options(string $route, callable $callback, array $middlewares = [])` | Registers a route. |
| `add_api_route(string $route, string\|array $methods, callable $callback, array $middlewares = [])` | Multi-verb route (`'GET\|POST'`). |
| `default_route(int $http_code = 404, ?callable $handler = null)` | Customizes the default response. |
| `include_controller(object\|string $controller, ?string $prefix = null, array $args = [])` | Mounts all routes of a controller. |
| `include_routes(string $class, ?string $prefix = null, array $args = [])` | Historical alias of `include_controller()`. |
| `include_router($router, ?string $router_class = null, array $args = [], ?string $prefix = '')` | Mounts an `APIRouter`. |
| `register_routes(string $class, array $args = [])` | Class carrying `__register_routes(EorbahAPI $app)`. |
| `add_middleware(object\|string $middleware, array $options = [])` | Global middleware. |
| `mount(string $prefix, $app, $name = null)` | Mounts a sub-application (`EorbahAPI`, `StaticFiles`, `JsonRPC`, callable). |
| `setRequestResponse(Request $request, Response $response)` | Replaces the current request/response. |
| `register(string $id, mixed $value)` | Registers a service in the container. |
| `setExceptionHandler(string $class, callable $handler)` | Registers an exception handler. |
| `router` | Underlying `Eorbahapi\APIRouter`. |
| `request`, `response` | Current request and response. |

## `Eorbahapi\APIRouter`

| Member | Role |
| --- | --- |
| `__construct(?string $prefix = '', array $tags = [])` | The prefix is applied to each route. |
| `get/post/put/patch/delete/head/options(...)` | Registration. |
| `add_api_route(string $route, string\|array $methods, callable $callback, array $middlewares = [])` | Multi-verb registration. |
| `match(string $uri, string $method): ?array` | `['callback', 'middlewares', 'params']` or `null`. |
| `routesFor(string $method): array` | Routes registered for a verb. |
| `dispatch(Request $request, ?Response $response = null, ?DependencyResolver $resolver = null): ?Response` | Runs the route and returns the response. |
| `static toResponse(mixed $result, Response $response): Response` | Normalizes a handler return value. |
| `static normalizeMiddleware(mixed $config): array` | `['class' => ..., 'args' => [...]]`. |
| `static makeMiddleware(array $config): object` | Instantiates a middleware. |
| `static invokeMiddleware(object $mw, Request $request, Response $response, callable $next): mixed` | Calls `process()`, `__invoke()` or `handle()`. |

## `Eorbahapi\Request`

```text
new Request(
    body: array|string|null,   // corps décodé ou JSON brut
    params: array,             // paramètres de route
    server: ?array,            // surcharge de $_SERVER
    query: ?array,             // surcharge de $_GET
    postData: ?array,          // surcharge de $_POST
    cookies: ?array,           // surcharge de $_COOKIE
    files: ?array,             // surcharge de $_FILES
    headers: ?array,           // en-têtes déjà extraits
);
```

| Method | Role |
| --- | --- |
| `method()`, `uri()`, `path()`, `pathInfo()`, `query_string()` | Request information. |
| `query(?string $key = null, $default = null)` | URL parameters. |
| `post()`, `form()` | Form data. |
| `body(?string $key = null, $default = null)`, `json()` | Body (automatically decoded). |
| `input(?string $key = null, $default = null)` | Merged body + form + query. |
| `getHeader($key = null)`, `header($key = null)` | Headers (case-insensitive, `_`/`-`). |
| `cookie($key = null)`, `file($key = null)` | Cookies and files. |
| `params(...)`, `param(string $key, mixed $default = null)` | Route parameters (and merged query). |
| `withUri(string $uri): static` | Copy pointing to another URI. |
| `server(?string $key = null, mixed $default = null)` | `$_SERVER` values. |
| `getBearerToken()`, `getClientIP()`, `userAgent()`, `isSocialBot()`, `checkRequestOrigin()` | Helpers. |
| `session($key = null, $default = null)`, `setSessionValue()`, `getSession()` | PHP session. |

## `Eorbahapi\Response`

Public properties: `body` (`string|array|null`), `status` (`int`), `headers` (`array`).

| Method | Role |
| --- | --- |
| `status()`, `body()`, `headers()`, `text()` | Reading. |
| `send()` | Sends status + headers + body. |
| `set_content_type(string $type, ?int $cacheMaxAge = null, array $options = [])` | MIME, cache, disposition. |
| `setHeader($name, $value)`, `header($name, $value)`, `removeHeader($name)` | Headers. |
| `cookie(string $name, $value, array $options = [])`, `clearCookie(string $name)` | Cookies. |

### Specialized responses

| Class | Usage |
| --- | --- |
| `Responses\JSONResponse($data, int $status = 200, array $headers = [])` | JSON body. |
| `Responses\HTMLResponse(string $content, int $status = 200, array $headers = [])` | HTML body. |
| `Responses\RedirectResponse(string $url, int $status = 302)` | Redirect. |
| `Responses\StreamingResponse(\Generator $generator, int $status = 200, array $headers = [])` | Stream. |
| `Responses\EventStreamResponse(\Generator $generator, ...)` | Server-Sent Events. |
| `Responses\FileResponse` | File response (free body). |

## Parameter attributes — `Eorbahapi\Params`

All extend `Svinx\Validator\FieldInfo`: `default`, `default_factory`, `alias`,
`title`, `description`, `gt`, `ge`, `lt`, `le`, `multiple_of`, `min_length`,
`max_length`, `pattern`, `choices`, `extra`. The first argument is always the
wire name.

| Attribute | Source |
| --- | --- |
| `Path` | path placeholder `{id}` |
| `Query` | `$_GET` |
| `Header` | headers (case-insensitive, `_` → `-`) |
| `Cookie` | `$_COOKIE` |
| `Body` | JSON body (Svinx model or field) |
| `Form` | `$_POST` |
| `File` | `$_FILES` |
| `Security` | security scheme |
| `Depends` | dependency (`dependency`, `args`) |

## Route attributes — `Eorbahapi\Routing\Attributes`

| Attribute | Role |
| --- | --- |
| `Route(string $route, array $methods = ['GET'], array $middlewares = [])` | Generic route. |
| `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options` | Fixed verb. |
| `Middleware(string $middleware, array $args = [])` | Method or class middleware (repeatable). |

The historical classes `Eorbahapi\Routing\{Route, Get, Post, ...}` are still
detected.

## Middlewares — `Eorbahapi\Middlewares`

| Class | Role |
| --- | --- |
| `MiddlewareInterface` | `__invoke(Request, Response, callable $next): mixed` |
| `BaseHTTPMiddleware` | Base to extend. |
| `CORSMiddleware(array $allow_origins = ['*'], bool $allow_credentials = false, array $allow_methods = ['*'], array $allow_headers = ['*'])` | CORS. |
| `SessionMiddleware(array $options = [])` | PHP session. |
| `TrustedHostMiddleware` | Allowed hosts. |
| `RateLimitingMiddleware(array $redis_config = [...], int $maxRequests = 100, int $timeWindow = 60, bool $routeSpecific = true, ?string $customPrefix = null)` | Rate limiting via Redis. |

## Dependencies and security

| Class | Role |
| --- | --- |
| `Dependency\DependencyInterface` | `resolve(Request $request, Response $response): mixed`. |
| `Security\HTTP\HTTPBearer`, `HTTPBasic` | HTTP schemes. |
| `Security\HTTP\HTTPAuthorizationCredentials` | `getScheme()`, `getCredentials()`, `isBearer()`, `toArray()`. |
| `Security\APIKey\APIKeyHeader`, `APIKeyQuery`, `APIKeyCookie` | API keys. |
| `Security\OAuth2\OAuth2`, `OAuth2PasswordBearer`, `OAuth2AuthorizationCodeBearer`, `OpenIdConnect` | OAuth2 / OIDC. |
| `Security\JWTAuth\JWT` | `sign()`, `verify()`, `decode()`, `isExpired()`, `tokenPair()`. |
| `Security\Nonce` | CSP nonce. |

## Mounted applications

| Class | Role |
| --- | --- |
| `BaseMountedApplication` | `handle(?Request, ?Response): Response` + `process(): void`. |
| `StaticFiles(string $directory, array $options = [])` | Static files. |
| `RPC\JsonRPC(array $options = [])` | JSON-RPC 2.0 (`add_method()`). |
| `Templating\TwigTemplates(string $templateDir, ?string $cacheDir = null, bool $debug = false)` | Twig templates. |

## Testing — `Eorbahapi\Testing`

| Class | Role |
| --- | --- |
| `TestClient(EorbahAPI $app)` | `request()`, `get()`, `post()`, `put()`, `patch()`, `delete()`. |
| `TestResponse` | `status()`, `headers()`, `header($name)`, `body()`, `text()`, `json()`, `raw()`, `isSuccess()`, `isRedirect()`, `assertStatus()`, `assertSee()`. |

## Validation — `Svinx\Validator`

| Class | Role |
| --- | --- |
| `BaseModel` | Validated model (`model_dump()`, `model_validate()`, `model_schema()`). |
| `Field(...)` | Constraints of a model field. |
| `FieldValidator` | Custom validators (`before` / `after`). |
| `ValidationError` | Raised error, converted to `RequestValidationError` (422). |
| `Typing\Type`, `TypeAdapter` | Composite types (`list`, `map`, unions, literals). |
| `Validators\*` | `EmailStr`, `HttpUrl`, `UUID`, `Date`, `Datetime`, `Decimal`, `IPv4Address`, `IPv6Address`, `SecretStr`. |

## Errors

| Class | Default rendering |
| --- | --- |
| `Exceptions\HTTPException` | requested status + JSON `{error, status, message}` |
| `Exceptions\ValidationException` | 422 + `details` |
| `Exceptions\DependencyScopeError` | 500 (programming error) |
| `Exceptions\EorbahAPIError` | 500 |
| any other `\Throwable` | 500 `{error, status, message, debug}` |
