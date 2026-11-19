# Référence API — aide-mémoire

## Application

```php
$app = new EorbahAPI(
    string $title = 'EorbahAPI', string $description = '', bool $debug = false, ?string $prefix = '',
    bool $cache = false, ?string $cache_dir = null, ?int $cache_ttl = null,
    ?string $summary = null, string $version = '0.1.0', ?string $terms_of_service = null,
    ?array $contact = null, ?array $license_info = null,
    ?string $openapi_url = '/openapi.json', ?array $openapi_tags = null,
    ?array $openapi_external_docs = null,
    string $default_response_class = JSONResponse::class,   // rendu des retours array/object
    ?string $docs_url = '/docs', ?string $redoc_url = '/redoc',
    ?string $swagger_ui_oauth2_redirect_url = '/docs/oauth2-redirect',
    ?array $swagger_ui_init_oauth = null, ?array $swagger_ui_parameters = null,
);   // les routes de documentation sont enregistrées automatiquement
$app->set_response_class(string $class): self;   // validation + remplacement
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

// Cache
$app->cache_manager(): CacheManager;
$app->set_cache_manager(CacheManager $manager): self;

// Documentation OpenAPI
$app->openapi(array $options = []): array;                    // spec 3.1 (cache fichier si cache: true)
$app->openapi_json(array $options = [], bool $pretty = true): string;
$app->write_openapi(string $path = 'openapi.json', array $options = []): string;  // écrit le .json
$app->openapi_routes(
    string $prefix = '', array $options = [],
    string|false|null $openapi_url = null,   // chemin relatif au préfixe
    string|false|null $docs_url = null,
    string|false|null $redoc_url = null,
    string|false|null $oauth2_redirect_url = null,   // false = route désactivée
    array $openapi_options = [],             // options du générateur (title, version, servers…)
    array $swagger_options = [],             // options de Swagger UI (swagger_js_url…)
    array $redoc_options = [],               // options de ReDoc (redoc_js_url…)
): self;
$app->docs(array $options = []): HTMLResponse;                // Swagger UI
$app->redoc(array $options = []): HTMLResponse;
$app->swagger_ui_oauth2_redirect_html(): HTMLResponse;
$app->documentation_routes(): list<string>;
$app->mounted_apps(): array;

$app->router;   // APIRouter
$app->request; $app->response;
```

## Cache

```php
$cache = new CacheManager(bool $enabled = false, ?CacheInterface $store = null, ?string $directory = null, int $ttl = 0);
$cache->enabled(): bool; $cache->store(): CacheInterface; $cache->directory(): ?string; $cache->ttl(): int;
$cache->get|set|has|delete|clear|remember(...);               // remember($key, $callback, ?$ttl = null)

new FileCache(string $directory, int $ttl = 0, string $namespace = 'eorbahapi');  // fichiers PHP atomiques
new ArrayCache(); new NullCache();

// Défaut : new EorbahAPI()                         -> NullCache
// new EorbahAPI(cache: true)                       -> cache/eorbahapi (FileCache)
// new EorbahAPI(cache: true, cache_dir: '/var/cache/api', cache_ttl: 3600)
```

## OpenAPI

```php
$spec = (new OpenApiBuilder($app, array $options = []))->build(): array;
$builder->fingerprint(): string;        // clé de cache (routes + options + mtime des fichiers)
$builder->collectRoutes(): array;       // routes + sous-applications montées

Utils::get_openapi($app, array $options = []): array;
Utils::get_openapi_json($app, array $options = [], bool $pretty = true): string;
Utils::write_openapi($app, string $path = 'openapi.json', array $options = []): string;

Docs::get_swagger_ui_html(string $openapi_url = '/openapi.json', ?string $title = null, ?string $oauth2_redirect_url = null, array $options = []): string;
Docs::get_redoc_html(string $openapi_url = '/openapi.json', ?string $title = null, array $options = []): string;
Docs::get_swagger_ui_oauth2_redirect_html(): string;
```

Options du générateur : `title`, `description`, `version`, `servers`,
`operations` (clé `"GET /users/{id}"` → `summary` / `description` / `tags`),
`components`, `securitySchemes`.

## Routeur

```php
$router = new APIRouter(?string $prefix = '', array $tags = []);
$router->add_api_route(string $route, string|array $methods, callable $callback, array $middlewares = []): void;
$router->match(string $uri, string $method): ?array;   // ['callback', 'middlewares', 'params']
$router->dispatch(Request $request, ?Response $response = null, ?DependencyResolver $resolver = null): ?Response;
$router->routesFor(string $method): array;

APIRouter::toResponse(mixed $result, Response $response, ?string $responseClass = null): Response;
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

## WebSockets

```php
$app->websocket(string $route, callable $handler): self;
$app->websocket_routes(): WebSocketRouter;

new WebSocketRouter(); $router->add(string $route, callable $handler);
$router->match(string $path): ?array; $router->has(string $path): bool; $router->count(): int;

new WebSocketServer(string $host = '127.0.0.1', int $port = 8081);
$server->run(WebSocketRouter|EorbahAPI $routes): void;
$server->handle(resource $stream, WebSocketRouter $router): void;

WebSocket::computeAccept(string $key): string;
$ws->accept(); $ws->send(string); $ws->send_json(mixed); $ws->send_bytes(string);
$ws->receive(): ?string; $ws->receive_json(): ?array; $ws->receive_bytes(): ?string;
$ws->close(int $code = 1000, string $reason = ''); $ws->state(): WebSocketState;
$ws->path(); $ws->params(); $ws->query(); $ws->headers(); $ws->client(); $ws->server();
```

## Tâches, logs, utilitaires

```php
$tasks = new BackgroundTasks();
$tasks->add(callable $task, array $args = []): self; $tasks->add_task(...): self;
$tasks->run(): void; $tasks->count(): int; $tasks->is_empty(): bool; $tasks->clear(): void;
$tasks->errors(): list<Throwable>; $tasks->clear_errors(): void;
new BackgroundTasksMiddleware(BackgroundTasks $tasks);

$logger = new Logger(string $logLevel = 'info');
$logger->log(string $level, string $message, array $context = []): void;
$logger->debug|info|warning|error|critical(string $message, array $context = []): void;

Status::HTTP_404_NOT_FOUND; Status::text(int $code, string $default = 'Unknown Status Code'): string;
Status::isSuccess/isRedirect/isClientError/isServerError/isError(int $code): bool;

Utils::getRoutePath(string $route, array $params = []): string;
Utils::normalizePath(string $path): string;
Utils::isAsyncCallable(callable $callback): bool;
```

## Contrats et adaptateurs

`EorbahAPI` implémente directement `ESGIAppInterface`.

```php
interface Eorbahapi\Contracts\ESGIAppInterface { __invoke(RequestInterface, ResponseInterface): ResponseInterface; }
interface Eorbahapi\Contracts\ServerAdapterInterface { run(ESGIAppInterface, string $host = '127.0.0.1', int $port = 8000, bool $reload = false): void; }
interface Eorbahapi\Contracts\CacheAdapterInterface extends CacheInterface { driver(): string; }

new Eorbahapi\Adapter\Http\RequestAdapter(Request $request);   // implements RequestInterface
new Eorbahapi\Adapter\Http\ResponseAdapter(Response $response); // create/from/unwrap
new Eorbahapi\Adapter\Datastructures\UploadFileAdapter(UploadFile $file);
new Eorbahapi\Adapter\Cache\MemoryCacheAdapter(); new FileCacheAdapter(string $dir, int $ttl = 0); new RedisCacheAdapter(array|object $config = []);
new Eorbahapi\Adapter\Server\MinimalServerAdapter(); new SwooleServerAdapter(int $workers = 1);

new Eorbahapi\Server\ESGIAppRunner(?ESGIAppInterface $app = null, ?ServerAdapterInterface $adapter = null);
$runner->run(?ESGIAppInterface $app = null, string $host = '127.0.0.1', int $port = 8000, bool $reload = false, ?ServerAdapterInterface $serverAdapter = null): void;
```

Les interfaces historiques (`Middlewares\MiddlewareInterface`,
`Dependency\DependencyInterface`, `Testing\TestClientInterface`) héritent des
contrats : le code existant les satisfait déjà.
