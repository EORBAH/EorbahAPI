# Référence API — aide-mémoire

## Application

```php
$app = new EorbahAPI(?string $title = null, ?string $description = null, bool $debug = false, ?string $prefix = '');
$app->run(?Request $request = null): void;                    // envoie
$app->handle(Request $request, ?Response $response = null): Response; // ne fait pas d'envoi
$app->get|post|put|patch|delete|head|options(string $route, callable $callback, array $middlewares = []): void;
$app->add_api_route(string $route, string|array $methods, callable $callback, array $middlewares = []): void;
$app->default_route(int $http_code = 404, ?callable $handler = null): void;
$app->include_controller(object|string $controller, ?string $prefix = null, array $args = []): self;
$app->include_routes(string $class, ?string $prefix = null, array $args = []): self;      // alias
$app->include_router($router, ?string $router_class = null, array $args = [], ?string $prefix = ''): void;
$app->add_middleware(object|string $middleware, array $options = []): self;
$app->mount(string $prefix, mixed $app, $name = null): self;
$app->register(string $id, mixed $value): self;
$app->setExceptionHandler(string $class, callable $handler): self;
$app->setRequestResponse(Request $request, Response $response): void;
$app->router;   // APIRouter
$app->request; $app->response;
```

## Routeur

```php
$router = new APIRouter(?string $prefix = '', array $tags = []);
$router->add_api_route(string $route, string|array $methods, callable $callback, array $middlewares = []): void;
$router->match(string $uri, string $method): ?array;   // ['callback', 'middlewares', 'params']
$router->dispatch(Request $request, ?Response $response = null, ?DependencyResolver $resolver = null): ?Response;
$router->routesFor(string $method): array;

APIRouter::toResponse(mixed $result, Response $response): Response;
APIRouter::normalizeMiddleware(mixed $config): array;   // ['class' => ..., 'args' => [...]]
APIRouter::makeMiddleware(array $config): object;
APIRouter::invokeMiddleware(object $mw, Request $request, Response $response, callable $next): mixed;
```

Retours de handler normalisés : `Response` → tel quel · `array|object` → JSON ·
`'redirect:code:url'` → redirection · scalaire → corps · `null|true|false` → réponse inchangée.

## Requête

```php
new Request(
    body: array|string|null, params: array = [],
    server: ?array, query: ?array, postData: ?array,
    cookies: ?array, files: ?array, headers: ?array,
);

$r->method(): string; $r->uri(): string; $r->path(): string; $r->pathInfo(): ?string;
$r->query(?string $key = null, $default = null);
$r->post(); $r->form();
$r->body(?string $key = null, $default = null); $r->json();
$r->input(?string $key = null, $default = null);
$r->getHeader($key = null); $r->header($key = null);
$r->cookie($key = null); $r->file($key = null);
$r->params(array|null|string $path = null); $r->param(string $key, mixed $default = null);
$r->withUri(string $uri): static;
$r->server(?string $key = null, mixed $default = null);
$r->getBearerToken(); $r->getClientIP(); $r->userAgent();
$r->session($key = null, $default = null); $r->setSessionValue($key, $value);
```

## Réponse

```php
$response->body;    // string|array|null
$response->status;  // int
$response->headers; // array

$response->send(): void;
$response->status(): int; $response->body(): string|array|null;
$response->headers(): array; $response->text(): string;
$response->set_content_type(string $type = 'html', ?int $cacheMaxAge = null, array $options = []): void;
$response->setHeader($name, $value); $response->header($name, $value); $response->removeHeader($name);
$response->cookie(string $name, $value, array $options = []); $response->clearCookie(string $name);

new JSONResponse(mixed $data, int $status = 200, array $headers = []);
new HTMLResponse(string $content, int $status = 200, array $headers = []);
new RedirectResponse(string $url, int $status = 302);
new StreamingResponse(\Generator $generator, int $status = 200, array $headers = []);
new EventStreamResponse(\Generator $generator, int $status = 200, array $headers = []);
```

## Attributs de paramètre

Namespace `Eorbahapi\Params`. Premier argument = nom sur le fil ; toutes les
contraintes de `Svinx\Validator\FieldInfo` sont disponibles
(`default`, `default_factory`, `alias`, `title`, `description`, `gt`, `ge`, `lt`,
`le`, `multiple_of`, `min_length`, `max_length`, `pattern`, `choices`, `extra`).

```php
#[Path(ge: 1)] int $id                       // placeholder {id}
#[Query(ge: 1, le: 100)] int $limit = 10     // $_GET
#[Header('X-Api-Key', min_length: 8)] string $key
#[Cookie(choices: ['light','dark'])] string $theme = 'light'
#[Body] Product $product                     // modèle Svinx
#[Body(name: 'email')] string $email         // champ du corps
#[Form(ge: 18)] int $age                     // $_POST
#[File] UploadFile $avatar                   // $_FILES
#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials
#[Depends(Service::class, args: ['x' => 1])] Service $service
```

`#[Security]` accepte `dependency` (schéma ou callable) et `scopes` (métadonnée).
`#[Depends]` accepte `dependency` (`Classe::class`, `'fonction'`, `[Classe::class,'methode']`) et `args`.

## Attributs de route

Namespace `Eorbahapi\Routing\Attributes` :

```php
#[Route('/x', methods: ['GET', 'POST'], middlewares: [])]
#[Get('/x')] #[Post('/x')] #[Put('/x')] #[Patch('/x')] #[Delete('/x')] #[Head('/x')] #[Options('/x')]
#[Middleware(RequestIdMiddleware::class, args: ['name' => 'x'])]   // méthode ou classe, répétable
```

## Middlewares

```php
interface MiddlewareInterface {
    public function __invoke(Request $request, Response $response, callable $next): mixed;
}
```
Styles acceptés : `__invoke(...)`, `process(...)`, `handle(...)`.
Retourner une `Response` court-circuite la chaîne.

| Classe | Constructeur |
| --- | --- |
| `CORSMiddleware` | `(array $allow_origins = ['*'], bool $allow_credentials = false, array $allow_methods = ['*'], array $allow_headers = ['*'])` |
| `SessionMiddleware` | `(array $options = [])` |
| `TrustedHostMiddleware` | `(array $allowed_hosts = [])` |
| `RateLimitingMiddleware` | `(array $redis_config = [...], int $maxRequests = 100, int $timeWindow = 60, bool $routeSpecific = true, ?string $customPrefix = null)` |

## Sécurité

```php
new HTTPBearer(bool $autoError = true, string $scheme = 'Bearer', ?string $bearerFormat = null, string $description = '');
new HTTPBasic(bool $autoError = true, string $scheme = 'Basic', string $realm = '');
new APIKeyHeader(string $headerName = 'X-API-Key', bool $autoError = true);
new APIKeyQuery(string $queryParamName = 'api_key', bool $autoError = true);
new APIKeyCookie(string $cookieName = 'api_key', bool $autoError = true);
new OAuth2(string $tokenUrl = '', array $scopes = [], bool $autoError = true, string $schemeName = 'OAuth2', ?string $description = null);
new JWT(string $secret, string $algorithm = 'HS256');   // sign/verify/decode/isExpired/tokenPair
```

Échec → `HTTPException` : 401 (+ `WWW-Authenticate`) pour Bearer/Basic/OAuth2,
403 pour les clés d'API.

## Applications montées

```php
class MonApp extends BaseMountedApplication {
    public function process(): void { /* remplit $this->response */ }
}
new StaticFiles(string $directory, array $options = []);  // index, cache_control, compression, allowed_extensions
new JsonRPC(array $options = []); $rpc->add_method(string $name, callable $callback);
```

## Tests

```php
$client = new TestClient($app);
$client->request(string $method, string $uri, array $options = []): TestResponse;
$client->get|post|put|patch|delete(string $uri, array $options = []): TestResponse;

// options : headers, query, json, form, body, cookies, files, server
$response->status(); $response->headers(); $response->header($name);
$response->body(); $response->text(); $response->json(); $response->raw();
$response->isSuccess(); $response->isRedirect();
$response->assertStatus(int $code); $response->assertSee(string $needle);
```
