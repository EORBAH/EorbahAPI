# Référence API

Cette page résume la surface publique du framework. Chaque section renvoie au
chapitre du cours qui la détaille.

## `Eorbahapi\EorbahAPI`

```php
<?php

$app = new EorbahAPI(
    string $title = 'EorbahAPI',
    string $description = '',
    bool $debug = false,
    ?string $prefix = '',
    bool $cache = false,
    ?string $cache_dir = null,
    ?int $cache_ttl = null,
    ?string $summary = null,
    string $version = '0.1.0',
    ?string $terms_of_service = null,
    ?array $contact = null,
    ?array $license_info = null,
    ?string $openapi_url = '/openapi.json',
    ?array $openapi_tags = null,
    ?array $openapi_external_docs = null,
    string $default_response_class = JSONResponse::class,
    ?string $docs_url = '/docs',
    ?string $redoc_url = '/redoc',
    ?string $swagger_ui_oauth2_redirect_url = '/docs/oauth2-redirect',
    ?array $swagger_ui_init_oauth = null,
    ?array $swagger_ui_parameters = null,
);
```

| Membre | Rôle |
| --- | --- |
| `run(?Request $request = null): void` | traite et **envoie** la réponse |
| `handle(Request $request, ?Response $response = null): Response` | traite et **retourne** la réponse |
| `get/post/put/patch/delete/head/options(string $route, callable $callback, array $middlewares = [])` | route HTTP |
| `add_api_route(string $route, string\|array $methods, callable $callback, array $middlewares = [])` | route multi-verbes |
| `default_route(int $http_code = 404, ?callable $handler = null)` | réponse par défaut |
| `include_controller(object\|string $controller, ?string $prefix = null, array $args = [])` | contrôleur par attributs |
| `include_routes(string $class, ...)` | alias historique |
| `include_router($router, ?string $router_class = null, array $args = [], ?string $prefix = '')` | router |
| `register_routes(string $class, array $args = [])` | classe `__register_routes()` |
| `add_middleware(object\|string $middleware, array $options = [])` | middleware global |
| `mount(string $prefix, mixed $app, $name = null)` | sous-application |
| `setRequestResponse(Request, Response)` | requête/réponse courantes |
| `register(string $id, mixed $value)` | conteneur |
| `setExceptionHandler(string $class, callable $handler)` | gestionnaire d'exception |
| `websocket(string $route, callable $handler)` | route WebSocket |
| `websocket_routes(): WebSocketRouter` | routeur WebSocket |
| `cache_manager(): CacheManager` | façade de cache |
| `set_cache_manager(CacheManager $manager)` | injecte un magasin |
| `openapi(array $options = []): array` | spécification OpenAPI |
| `openapi_json(array $options = [], bool $pretty = true): string` | spec JSON |
| `openapi_options(): array` | métadonnées OpenAPI du constructeur |
| `openapi_routes(string $prefix = '', array $options = [], ..., array $openapi_options = [], array $swagger_options = [], array $redoc_options = [])` | placement avancé des pages |
| `write_openapi(string $path = 'openapi.json', array $options = []): string` | écrit le `.json` |
| `docs()`, `redoc()`, `swagger_ui_oauth2_redirect_html()` | pages HTML |
| `documentation_routes(): list<string>` | routes de doc masquées du schéma |
| `mounted_apps(): array` | sous-applications montées |
| `response_class` | classe de rendu des données |
| `set_response_class(string $class)` | remplace la classe de rendu |
| `router`, `request`, `response` | objets courants |

## `Eorbahapi\APIRouter`

```php
<?php

$router = new APIRouter(?string $prefix = '', array $tags = []);
$router->add_api_route(string $route, string|array $methods, callable $callback, array $middlewares = []): void;
$router->match(string $uri, string $method): ?array;   // ['callback', 'middlewares', 'params']
$router->routesFor(string $method): array;
$router->dispatch(Request $request, ?Response $response = null, ?DependencyResolver $resolver = null): ?Response;

APIRouter::toResponse(mixed $result, Response $response, ?string $responseClass = null): Response;
APIRouter::normalizeMiddleware(mixed $config): array;
APIRouter::makeMiddleware(array $config): object;
APIRouter::invokeMiddleware(object $mw, Request $request, Response $response, callable $next): mixed;
```

## `Eorbahapi\Request` / `Eorbahapi\Response`

```php
<?php

new Request(
    body: array|string|null = null, params: array = [],
    server: ?array = null, query: ?array = null, postData: ?array = null,
    cookies: ?array = null, files: ?array = null, headers: ?array = null,
);

$r->method(); $r->uri(); $r->path(); $r->pathInfo(); $r->query_string();
$r->query(?string $key = null, $default = null); $r->post(); $r->body(...); $r->json();
$r->input(...); $r->getHeader($key = null); $r->header($key = null); $r->cookie(...); $r->file(...);
$r->params(array|null|string $path = null); $r->param(string $key, mixed $default = null);
$r->server(?string $key = null, mixed $default = null); $r->getBearerToken();
$r->getClientIP(array $trustedProxies = []); $r->checkRequestOrigin($allowedDomains = null);
$r->session(...); $r->setSessionValue(...); $r->userAgent(); $r->isSocialBot();
$r->withUri(string $uri); $r->withBody(array|string|null $body);
```

```php
<?php

$response = new Response(
    string|array|null $body = null,
    int $status = 200,
    array $headers = [],
);

$response->send(); $response->status(); $response->body(); $response->headers(); $response->text();
$response->setHeader($name, $value); $response->header($name, $value); $response->removeHeader($name);
$response->cookie($name, $value, $options = []); $response->clearCookie($name);
$response->set_content_type(string $type = 'html', ?int $cacheMaxAge = null, array $options = []);
```

## `Eorbahapi\Responses`

| Classe | Constructeur |
| --- | --- |
| `JSONResponse` | `(mixed $data, int $status = 200, array $headers = [])` |
| `HTMLResponse` | `(string $content, int $status = 200, array $headers = [], bool $setContentType = true)` |
| `RedirectResponse` | `(string $url, int $status = 302)` |
| `StreamingResponse` | `(\Generator $generator, int $status = 200, array $headers = [])` |
| `FileResponse` | `(string\|UploadFile\|resource $file, int $status = 200, array $headers = [], ?string $filename = null, string $disposition = 'inline', ?string $mediaType = null, ?Request $request = null)` |
| `EventStreamResponse` | idem + en-têtes SSE |

## Attributs de paramètre — `Eorbahapi\Params`

| Attribut | Emplacement |
| --- | --- |
| `Path`, `Query`, `Header`, `Cookie` | chemin, query, en-tête, cookie |
| `Body`, `Form`, `File` | corps JSON, formulaire, fichier |
| `Security` | schéma de sécurité (`#[Security(HTTPBearer::class)]`) |
| `Depends` | dépendance (`#[Depends('fn')]`, `#[Depends(Classe::class)]`) |

Tous héritent de `FieldInfo` : `gt/gte`, `lt/le`, `min_length`, `max_length`,
`pattern`, `choices`, `default`, `alias`.

## Attributs de route — `Eorbahapi\Routing\Attributes`

`Route`, `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options`,
`Middleware(string $middleware, array $args = [])` (répétable, classe ou méthode).

Les classes historiques `Eorbahapi\Routing\{Route, Get, ...}` restent détectées.

## Contrats — `Eorbahapi\Contracts`

`RequestInterface`, `ResponseInterface`, `ESGIAppInterface`,
`MiddlewareInterface`, `DependencyInterface`, `TestClientInterface`,
`ServerAdapterInterface`, `CacheAdapterInterface`, `UploadFileInterface`,
`StreamInterface`, `UriInterface`, `WebSocket\WebSocketInterface` (+
`WebSocketSendMessage`, `WebSocketReceiveMessage`, `WebSocketScope`).

Les interfaces historiques (`Middlewares\MiddlewareInterface`,
`Dependency\DependencyInterface`, `Testing\TestClientInterface`) en héritent.

## Adaptateurs — `Eorbahapi\Adapter`

`EorbahAPI` implémente directement `Eorbahapi\Contracts\ESGIAppInterface`.

| Classe | Rôle |
| --- | --- |
| `Http\RequestAdapter`, `Http\ResponseAdapter` | implémentent les contrats |
| `Datastructures\UploadFileAdapter` | `UploadFileInterface` |
| `Cache\MemoryCacheAdapter`, `Cache\FileCacheAdapter`, `Cache\RedisCacheAdapter` | `CacheAdapterInterface` |
| `Server\MinimalServerAdapter` | requête courante (FPM, `php -S`) |
| `Server\SwooleServerAdapter(int $workers = 1, string $logLevel = 'info', bool $accessLog = true, ?callable $logger = null)` | serveur Swoole HTTP + WebSocket sur un seul port (logs façon uvicorn) |

## Serveur — `Eorbahapi\Server`

`ESGIAppRunner(?ESGIAppInterface $app = null, ?ServerAdapterInterface $adapter = null)` :
`run(?ESGIAppInterface $app = null, string $host = '127.0.0.1', int $port = 8000, bool $reload = false, ?ServerAdapterInterface $serverAdapter = null)`,
`app()`, `adapter()`, `ESGIAppRunner::defaultAdapter()`.

## Cache — `Eorbahapi\Cache`

| Classe | Rôle |
| --- | --- |
| `CacheManager(bool $enabled = false, ?CacheInterface $store = null, ?string $directory = null, int $ttl = 0)` | `enabled()`, `store()`, `remember()`, `get/set/has/delete/clear` |
| `CacheInterface` | contrat |
| `FileCache(string $directory, int $ttl = 0, string $namespace = 'eorbahapi')` | fichiers PHP |
| `ArrayCache` | mémoire |
| `NullCache` | désactivé |

## OpenAPI — `Eorbahapi\Openapi`

`OpenApiBuilder(EorbahAPI $app, array $options = [])` (`build()`, `fingerprint()`,
`collectRoutes()`), `Models\SchemaRegistry`, `Models\Parameter`,
`Models\RequestBody`, `Models\SecuritySchemes`, `Models\TypeSchema`,
`Docs` (`get_swagger_ui_html`, `get_redoc_html`,
`get_swagger_ui_oauth2_redirect_html`), `Utils`
(`get_openapi`, `get_openapi_json`, `write_openapi`), `Constants`.

## WebSockets — `Eorbahapi\Websockets`

`WebSocket` (`send`, `send_json`, `send_bytes`, `receive`, `receive_json`,
`receive_bytes`, `close`, `state`, `path`, `params`, `query`, `headers`,
`client`, `server`), `WebSocketRouter` (`add`, `match`, `has`, `routes`,
`count`), `WebSocketServer(string $host = '127.0.0.1', int $port = 8081)`
(`run(WebSocketRouter|EorbahAPI $routes)`, `handle($stream, WebSocketRouter)`),
`WebSocketState` (enum), `WebSocketDisconnect(int $closeCode, string $reason)`.

## Tâches, journalisation, utilitaires

- `BackgroundTasks` : `add(callable, array $args = [])`, `add_task()`, `run()`,
  `count()`, `is_empty()`, `clear()`, `errors()`, `clear_errors()` ;
  middleware `Middlewares\BackgroundTasksMiddleware(BackgroundTasks $tasks)`.
- `Logger(string $logLevel = 'info')` : `log()`, `debug()`, `info()`,
  `warning()`, `error()`, `critical()`.
- `Status` : constantes `HTTP_*`, `text()`, `reason()`, `isSuccess()`,
  `isRedirect()`, `isClientError()`, `isServerError()`, `isError()`.
- `Utils` : `getRoutePath()`, `normalizePath()`, `isAsyncCallable()`.

## Middlewares — `Eorbahapi\Middlewares`

`MiddlewareInterface`, `BaseHTTPMiddleware`, `CORSMiddleware`, `SessionMiddleware`,
`TrustedHostMiddleware`, `RateLimitingMiddleware` (Redis), `BackgroundTasksMiddleware`.

## Sécurité — `Eorbahapi\Security`

`HTTP\HTTPBearer`, `HTTP\HTTPBasic`, `HTTP\HTTPAuthorizationCredentials`,
`APIKey\{APIKeyHeader,APIKeyQuery,APIKeyCookie}`, `OAuth2\{OAuth2,
OAuth2PasswordBearer,OAuth2AuthorizationCodeBearer,OpenIdConnect}`,
`JWTAuth\JWT`, `Nonce`.

## Dépendances — `Eorbahapi\Dependency`

`DependencyResolver` (`resolve`, `describe`, `set`, `setRequestResponse`),
`DependencyInterface`, `ParameterDescriptor`.

## Tests — `Eorbahapi\Testing`

`TestClient(EorbahAPI $app)` (`request`, `get`, `post`, `put`, `patch`,
`delete`), `TestResponse` (`status`, `headers`, `header`, `body`, `text`,
`json`, `raw`, `isSuccess`, `isRedirect`, `assertStatus`, `assertSee`),
`TestClientInterface`.

## Applications montées

`BaseMountedApplication` (`handle`, `process`, `run`), `StaticFiles(string
$directory, array $options = [])`, `RPC\JsonRPC(array $options = [])`
(`add_method`), `Templating\TwigTemplates(string $templateDir =
'templates', ?string $cacheDir = null, bool $debug = false)`.

## Structures — `Eorbahapi\Datastructures`

`UploadFile`, `Headers`, `FormData`, `Address` ; `State`, `URL`, `QueryParams`
sont des espaces réservés.

## Erreurs — `Eorbahapi\Exceptions`

| Classe | Rendu par défaut |
| --- | --- |
| `HTTPException($status, $message, $headers)` | statut demandé + JSON |
| `ValidationException` / `RequestValidationError` | 422 + `details` |
| `DependencyScopeError` | 500 |
| `EorbahAPIError` | 500 |
| `WebSocketException` | erreur WebSocket |
| toute autre `\Throwable` | 500 (`debug` en mode debug) |

## Validation — `Svinx\Validator`

`BaseModel` (`model_dump`, `model_dump_json`, `model_validate`,
`model_validate_json`, `model_schema`), `Field`, `FieldValidator`,
`ModelValidator`, `Config`, `ValidationError`, `Typing\Type`, `TypeAdapter`,
`Validators\{EmailStr, HttpUrl, UUID, Date, Datetime, Decimal, IPv4Address,
IPv6Address, SecretStr}`.
