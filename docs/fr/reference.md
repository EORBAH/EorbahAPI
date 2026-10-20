# Référence API

Cette page résume la surface publique du framework. Chaque entrée renvoie au
chapitre du cours qui la détaille.

## `Eorbahapi\EorbahAPI`

| Membre | Rôle |
| --- | --- |
| `__construct(?string $title, ?string $description, bool $debug = false, ?string $prefix = '')` | Crée l'application. |
| `run(?Request $request = null): void` | Traite la requête (réelle ou fournie) et **envoie** la réponse. |
| `handle(Request $request, ?Response $response = null): Response` | Traite la requête et **retourne** la réponse, sans envoi. |
| `get/post/put/patch/delete/head/options(string $route, callable $callback, array $middlewares = [])` | Enregistre une route. |
| `add_api_route(string $route, string\|array $methods, callable $callback, array $middlewares = [])` | Route multi-verbes (`'GET\|POST'`). |
| `default_route(int $http_code = 404, ?callable $handler = null)` | Personnalise la réponse par défaut. |
| `include_controller(object\|string $controller, ?string $prefix = null, array $args = [])` | Monte toutes les routes d'un contrôleur. |
| `include_routes(string $class, ?string $prefix = null, array $args = [])` | Alias historique de `include_controller()`. |
| `include_router($router, ?string $router_class = null, array $args = [], ?string $prefix = '')` | Monte un `APIRouter`. |
| `register_routes(string $class, array $args = [])` | Classe portant `__register_routes(EorbahAPI $app)`. |
| `add_middleware(object\|string $middleware, array $options = [])` | Middleware global. |
| `mount(string $prefix, $app, $name = null)` | Monte une sous-application (`EorbahAPI`, `StaticFiles`, `JsonRPC`, callable). |
| `setRequestResponse(Request $request, Response $response)` | Remplace requête/réponse courantes. |
| `register(string $id, mixed $value)` | Enregistre un service dans le conteneur. |
| `setExceptionHandler(string $class, callable $handler)` | Enregistre un gestionnaire d'exception. |
| `router` | `Eorbahapi\APIRouter` sous-jacent. |
| `request`, `response` | Requête et réponse courantes. |

## `Eorbahapi\APIRouter`

| Membre | Rôle |
| --- | --- |
| `__construct(?string $prefix = '', array $tags = [])` | Le préfixe est appliqué à chaque route. |
| `get/post/put/patch/delete/head/options(...)` | Enregistrement. |
| `add_api_route(string $route, string\|array $methods, callable $callback, array $middlewares = [])` | Enregistrement multi-verbes. |
| `match(string $uri, string $method): ?array` | `['callback', 'middlewares', 'params']` ou `null`. |
| `routesFor(string $method): array` | Routes enregistrées pour un verbe. |
| `dispatch(Request $request, ?Response $response = null, ?DependencyResolver $resolver = null): ?Response` | Exécute la route et retourne la réponse. |
| `static toResponse(mixed $result, Response $response): Response` | Normalise un retour de handler. |
| `static normalizeMiddleware(mixed $config): array` | `['class' => ..., 'args' => [...]]`. |
| `static makeMiddleware(array $config): object` | Instancie un middleware. |
| `static invokeMiddleware(object $mw, Request $request, Response $response, callable $next): mixed` | Appelle `process()`, `__invoke()` ou `handle()`. |

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

| Méthode | Rôle |
| --- | --- |
| `method()`, `uri()`, `path()`, `pathInfo()`, `query_string()` | Informations de requête. |
| `query(?string $key = null, $default = null)` | Paramètres d'URL. |
| `post()`, `form()` | Données de formulaire. |
| `body(?string $key = null, $default = null)`, `json()` | Corps (décodé automatiquement). |
| `input(?string $key = null, $default = null)` | Corps + formulaire + query fusionnés. |
| `getHeader($key = null)`, `header($key = null)` | En-têtes (insensible à la casse, `_`/`-`). |
| `cookie($key = null)`, `file($key = null)` | Cookies et fichiers. |
| `params(...)`, `param(string $key, mixed $default = null)` | Paramètres de route (et query fusionnée). |
| `withUri(string $uri): static` | Copie pointant sur une autre URI. |
| `server(?string $key = null, mixed $default = null)` | Valeurs `$_SERVER`. |
| `getBearerToken()`, `getClientIP()`, `userAgent()`, `isSocialBot()`, `checkRequestOrigin()` | Aides. |
| `session($key = null, $default = null)`, `setSessionValue()`, `getSession()` | Session PHP. |

## `Eorbahapi\Response`

Propriétés publiques : `body` (`string|array|null`), `status` (`int`), `headers` (`array`).

| Méthode | Rôle |
| --- | --- |
| `status()`, `body()`, `headers()`, `text()` | Lecture. |
| `send()` | Envoie statut + en-têtes + corps. |
| `set_content_type(string $type, ?int $cacheMaxAge = null, array $options = [])` | MIME, cache, disposition. |
| `setHeader($name, $value)`, `header($name, $value)`, `removeHeader($name)` | En-têtes. |
| `cookie(string $name, $value, array $options = [])`, `clearCookie(string $name)` | Cookies. |

### Réponses spécialisées

| Classe | Usage |
| --- | --- |
| `Responses\JSONResponse($data, int $status = 200, array $headers = [])` | Corps JSON. |
| `Responses\HTMLResponse(string $content, int $status = 200, array $headers = [])` | Corps HTML. |
| `Responses\RedirectResponse(string $url, int $status = 302)` | Redirection. |
| `Responses\StreamingResponse(\Generator $generator, int $status = 200, array $headers = [])` | Flux. |
| `Responses\EventStreamResponse(\Generator $generator, ...)` | Server-Sent Events. |
| `Responses\FileResponse` | Réponse fichier (corps libre). |

## Attributs de paramètre — `Eorbahapi\Params`

Tous étendent `Svinx\Validator\FieldInfo` : `default`, `default_factory`, `alias`,
`title`, `description`, `gt`, `ge`, `lt`, `le`, `multiple_of`, `min_length`,
`max_length`, `pattern`, `choices`, `extra`. Le premier argument est toujours le
nom sur le fil.

| Attribut | Source |
| --- | --- |
| `Path` | placeholder `{id}` du chemin |
| `Query` | `$_GET` |
| `Header` | en-têtes (insensible à la casse, `_` → `-`) |
| `Cookie` | `$_COOKIE` |
| `Body` | corps JSON (modèle Svinx ou champ) |
| `Form` | `$_POST` |
| `File` | `$_FILES` |
| `Security` | schéma de sécurité |
| `Depends` | dépendance (`dependency`, `args`) |

## Attributs de route — `Eorbahapi\Routing\Attributes`

| Attribut | Rôle |
| --- | --- |
| `Route(string $route, array $methods = ['GET'], array $middlewares = [])` | Route générique. |
| `Get`, `Post`, `Put`, `Patch`, `Delete`, `Head`, `Options` | Verbe fixé. |
| `Middleware(string $middleware, array $args = [])` | Middleware de méthode ou de classe (répétable). |

Les classes historiques `Eorbahapi\Routing\{Route, Get, Post, ...}` restent
détectées.

## Middlewares — `Eorbahapi\Middlewares`

| Classe | Rôle |
| --- | --- |
| `MiddlewareInterface` | `__invoke(Request, Response, callable $next): mixed` |
| `BaseHTTPMiddleware` | Base à étendre. |
| `CORSMiddleware(array $allow_origins = ['*'], bool $allow_credentials = false, array $allow_methods = ['*'], array $allow_headers = ['*'])` | CORS. |
| `SessionMiddleware(array $options = [])` | Session PHP. |
| `TrustedHostMiddleware` | Hôtes autorisés. |
| `RateLimitingMiddleware(array $redis_config = [...], int $maxRequests = 100, int $timeWindow = 60, bool $routeSpecific = true, ?string $customPrefix = null)` | Limitation via Redis. |

## Dépendances et sécurité

| Classe | Rôle |
| --- | --- |
| `Dependency\DependencyInterface` | `resolve(Request $request, Response $response): mixed`. |
| `Security\HTTP\HTTPBearer`, `HTTPBasic` | Schémas HTTP. |
| `Security\HTTP\HTTPAuthorizationCredentials` | `getScheme()`, `getCredentials()`, `isBearer()`, `toArray()`. |
| `Security\APIKey\APIKeyHeader`, `APIKeyQuery`, `APIKeyCookie` | Clés d'API. |
| `Security\OAuth2\OAuth2`, `OAuth2PasswordBearer`, `OAuth2AuthorizationCodeBearer`, `OpenIdConnect` | OAuth2 / OIDC. |
| `Security\JWTAuth\JWT` | `sign()`, `verify()`, `decode()`, `isExpired()`, `tokenPair()`. |
| `Security\Nonce` | Nonce CSP. |

## Applications montées

| Classe | Rôle |
| --- | --- |
| `BaseMountedApplication` | `handle(?Request, ?Response): Response` + `process(): void`. |
| `StaticFiles(string $directory, array $options = [])` | Fichiers statiques. |
| `RPC\JsonRPC(array $options = [])` | JSON-RPC 2.0 (`add_method()`). |
| `Templating\TwigTemplates(string $templateDir, ?string $cacheDir = null, bool $debug = false)` | Templates Twig. |

## Tests — `Eorbahapi\Testing`

| Classe | Rôle |
| --- | --- |
| `TestClient(EorbahAPI $app)` | `request()`, `get()`, `post()`, `put()`, `patch()`, `delete()`. |
| `TestResponse` | `status()`, `headers()`, `header($name)`, `body()`, `text()`, `json()`, `raw()`, `isSuccess()`, `isRedirect()`, `assertStatus()`, `assertSee()`. |

## Validation — `Svinx\Validator`

| Classe | Rôle |
| --- | --- |
| `BaseModel` | Modèle validé (`model_dump()`, `model_validate()`, `model_schema()`). |
| `Field(...)` | Contraintes d'un champ de modèle. |
| `FieldValidator` | Validateurs personnalisés (`before` / `after`). |
| `ValidationError` | Erreur levée, convertie en `RequestValidationError` (422). |
| `Typing\Type`, `TypeAdapter` | Types composés (`list`, `map`, unions, littéraux). |
| `Validators\*` | `EmailStr`, `HttpUrl`, `UUID`, `Date`, `Datetime`, `Decimal`, `IPv4Address`, `IPv6Address`, `SecretStr`. |

## Erreurs

| Classe | Rendu par défaut |
| --- | --- |
| `Exceptions\HTTPException` | statut demandé + JSON `{error, status, message}` |
| `Exceptions\ValidationException` | 422 + `details` |
| `Exceptions\DependencyScopeError` | 500 (erreur de programmation) |
| `Exceptions\EorbahAPIError` | 500 |
| toute autre `\Throwable` | 500 `{error, status, message, debug}` |
