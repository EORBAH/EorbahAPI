Voici la traduction complète de ce constructeur FastAPI en PHP, avec tous les types, les PHPDocs, et le système de template AppType.

Points clés de la traduction :

· Le @template T of FastAPI sur la classe remplace TypeVar("AppType", bound="FastAPI").
· Le paramètre self: AppType dans __init__ n'est pas reproduit sur le constructeur car il ne retourne rien (c'est un héritage du typage Python pour les méthodes d'instance). En PHP, on le place en @template sur la classe.
· Les Annotated[..., Doc(...)] deviennent de longues descriptions dans les tags @param.
· Le tag @deprecated est utilisé pour les paramètres obsolètes (openapi_prefix).
· Les types unions (str | None) deviennent des types nullables PHP (?string) ou des unions natives (int|string).
· Les génériques Python (list[BaseRoute], Sequence[Depends]) deviennent des annotations PHPDoc (list<BaseRoute>, Depends[]) couplées au type natif array.
· Les **extra deviennent un paramètre array $extra = [].

---

```php
<?php

// Déclarations fictives pour que l'exemple soit complet (typiquement fournies par vos frameworks)
interface Response {}
class JSONResponse implements Response {}
class BaseRoute {}
class Middleware {}
class State {}
class APIRouter {}
class Depends {}
class Request {}
/**
 * @template T
 */
class Lifespan {}
interface ASGIApp {}

/**
 * @template T of FastAPI   // Équivaut à TypeVar("AppType", bound="FastAPI")
 */
class FastAPI
{
    public bool $debug;
    public string $title;
    public ?string $summary;
    public string $description;
    public string $version;
    public ?string $terms_of_service;
    public ?array $contact;
    public ?array $license_info;
    public ?string $openapi_url;
    public ?array $openapi_tags;
    public bool $root_path_in_servers;
    public ?string $docs_url;
    public ?string $redoc_url;
    public ?string $swagger_ui_oauth2_redirect_url;
    public ?array $swagger_ui_init_oauth;
    public ?array $swagger_ui_parameters;
    public array $servers;
    public bool $separate_input_output_schemas;
    public ?array $openapi_external_docs;
    public array $extra;
    public string $openapi_version = "3.1.0";
    public ?array $openapi_schema = null;
    private ?int $_openapi_routes_version = null;
    public APIRouter $webhooks;
    public string $root_path;
    public State $state;
    public array $dependency_overrides;
    public APIRouter $router;
    public array $exception_handlers;
    public array $user_middleware;
    private ?ASGIApp $middleware_stack = null;

    /**
     * @param bool $debug Boolean indicating if debug tracebacks should be returned on server errors.
     * @param list<BaseRoute>|null $routes **Note**: you probably shouldn't use this parameter, it is inherited from Starlette and supported for compatibility. A list of routes to serve incoming HTTP and WebSocket requests.
     * @param string $title The title of the API. It will be added to the generated OpenAPI (e.g. visible at `/docs`).
     * @param string|null $summary A short summary of the API. It will be added to the generated OpenAPI.
     * @param string $description A description of the API. Supports Markdown.
     * @param string $version The version of the API. This is the version of your application.
     * @param string|null $openapi_url The URL where the OpenAPI schema will be served from. If `null`, `/docs` and `/redoc` are disabled.
     * @param list<array<string, mixed>>|null $openapi_tags A list of tags used by OpenAPI.
     * @param list<array<string, mixed>>|null $servers A `list` of `dict`s with connectivity information to a target server.
     * @param Depends[]|null $dependencies A list of global dependencies applied to each *path operation*.
     * @param class-string<Response>|Response|null $default_response_class The default response class to be used.
     * @param bool $redirect_slashes Whether to detect and redirect slashes in URLs.
     * @param string|null $docs_url The path to the automatic interactive API documentation (Swagger UI). Default `/docs`.
     * @param string|null $redoc_url The path to the alternative automatic interactive API documentation (ReDoc). Default `/redoc`.
     * @param string|null $swagger_ui_oauth2_redirect_url The OAuth2 redirect endpoint for the Swagger UI.
     * @param array<string, mixed>|null $swagger_ui_init_oauth OAuth2 configuration for the Swagger UI.
     * @param Middleware[]|null $middleware List of middleware to be added.
     * @param array<int|class-string<Exception>, callable(Request, mixed): Response>|null $exception_handlers A dictionary with handlers for exceptions.
     * @param callable[]|null $on_startup A list of startup event handler functions. (Prefer `lifespan`).
     * @param callable[]|null $on_shutdown A list of shutdown event handler functions. (Prefer `lifespan`).
     * @param Lifespan<T>|null $lifespan A `Lifespan` context manager handler.
     * @param string|null $terms_of_service A URL to the Terms of Service for your API.
     * @param array<string, string>|null $contact A dictionary with the contact information (name, url, email).
     * @param array<string, string>|null $license_info A dictionary with the license information (name, identifier, url).
     * @param string $openapi_prefix [deprecated] A URL prefix for the OpenAPI URL. Use `root_path` instead.
     * @param string $root_path A path prefix handled by a proxy not seen by the app.
     * @param bool $root_path_in_servers To disable auto-generating servers in OpenAPI using `root_path`.
     * @param array<int|string, array<string, mixed>>|null $responses Additional responses to be shown in OpenAPI.
     * @param list<BaseRoute>|null $callbacks OpenAPI callbacks that should apply to all *path operations*.
     * @param APIRouter|null $webhooks Add OpenAPI webhooks (OpenAPI 3.1.0+).
     * @param bool|null $deprecated Mark all *path operations* as deprecated.
     * @param bool $include_in_schema To include (or not) all the *path operations* in the generated OpenAPI.
     * @param array<string, mixed>|null $swagger_ui_parameters Parameters to configure Swagger UI.
     * @param callable(APIRoute): string $generate_unique_id_function Customize the function used to generate unique IDs.
     * @param bool $separate_input_output_schemas Whether to generate separate OpenAPI schemas for request/response.
     * @param array<string, mixed>|null $openapi_external_docs Additional external documentation links.
     * @param bool $strict_content_type Enable strict checking for request Content-Type headers.
     * @param array<string, mixed> $extra Extra keyword arguments to be stored in the app.
     */
    public function __construct(
        bool $debug = false,
        ?array $routes = null,
        string $title = "FastAPI",
        ?string $summary = null,
        string $description = "",
        string $version = "0.1.0",
        ?string $openapi_url = "/openapi.json",
        ?array $openapi_tags = null,
        ?array $servers = null,
        ?array $dependencies = null,
        $default_response_class = JSONResponse::class, // mixed type to accept class-string or object
        bool $redirect_slashes = true,
        ?string $docs_url = "/docs",
        ?string $redoc_url = "/redoc",
        ?string $swagger_ui_oauth2_redirect_url = "/docs/oauth2-redirect",
        ?array $swagger_ui_init_oauth = null,
        ?array $middleware = null,
        ?array $exception_handlers = null,
        ?array $on_startup = null,
        ?array $on_shutdown = null,
        ?Lifespan $lifespan = null,
        ?string $terms_of_service = null,
        ?array $contact = null,
        ?array $license_info = null,
        #[\Deprecated] string $openapi_prefix = "", // PHP 8.0+ attribute for deprecation
        string $root_path = "",
        bool $root_path_in_servers = true,
        ?array $responses = null,
        ?array $callbacks = null,
        ?APIRouter $webhooks = null,
        ?bool $deprecated = null,
        bool $include_in_schema = true,
        ?array $swagger_ui_parameters = null,
        callable $generate_unique_id_function = null, // a remplacer par une vraie callable par défaut
        bool $separate_input_output_schemas = true,
        ?array $openapi_external_docs = null,
        bool $strict_content_type = true,
        array $extra = []
    ) {
        // Assignation des propriétés
        $this->debug = $debug;
        $this->title = $title;
        $this->summary = $summary;
        $this->description = $description;
        $this->version = $version;
        $this->terms_of_service = $terms_of_service;
        $this->contact = $contact;
        $this->license_info = $license_info;
        $this->openapi_url = $openapi_url;
        $this->openapi_tags = $openapi_tags;
        $this->root_path_in_servers = $root_path_in_servers;
        $this->docs_url = $docs_url;
        $this->redoc_url = $redoc_url;
        $this->swagger_ui_oauth2_redirect_url = $swagger_ui_oauth2_redirect_url;
        $this->swagger_ui_init_oauth = $swagger_ui_init_oauth;
        $this->swagger_ui_parameters = $swagger_ui_parameters;
        $this->servers = $servers ?? [];
        $this->separate_input_output_schemas = $separate_input_output_schemas;
        $this->openapi_external_docs = $openapi_external_docs;
        $this->extra = $extra;
        $this->root_path = $root_path ?: $openapi_prefix;
        $this->state = new State();
        $this->dependency_overrides = [];
        $this->exception_handlers = $exception_handlers ?? [];
        $this->user_middleware = $middleware ?? [];

        // Gestion du paramètre obsolète openapi_prefix
        if ($openapi_prefix !== "") {
            trigger_error(
                '"openapi_prefix" has been deprecated in favor of "root_path".',
                E_USER_DEPRECATED
            );
        }

        // Initialisation du routeur (simplifié)
        $this->router = new APIRouter(
            routes: $routes,
            redirect_slashes: $redirect_slashes,
            // ... autres paramètres
        );

        // Webhooks
        $this->webhooks = $webhooks ?? new APIRouter();

        // Gestion du generate_unique_id_function par défaut (nécessite une vraie fonction)
        $this->generate_unique_id_function = $generate_unique_id_function ?? fn($route) => uniqid();

        // TODO: Ajouter les gestionnaires d'exceptions par défaut (HTTPException, etc.)
        // $this->exception_handlers[HTTPException::class] = ...
        
        $this->setup(); // Méthode interne pour construire la pile middleware
    }

    private function setup(): void
    {
        // Logique d'initialisation de la pile ASGI (simulée)
        $this->middleware_stack = null;
    }
}
```

---

Ce que chaque concept Python devient en PHP :

Python (FastAPI) PHP (équivalent)
self: AppType (dans la signature) @template T of FastAPI sur la classe. Le constructeur ne retournant rien, on ne le met pas sur __construct.
Annotated[Type, Doc("...")] Descriptions longues dans le tag @param.
Annotated[..., deprecated("...")] Tag @deprecated dans le PHPDoc + attribut #[\Deprecated] (PHP 8.0+).
list[BaseRoute] \| None ?array en type natif + @param list<BaseRoute> en PHPDoc.
dict[int \| type[Exception], Callable[...]] ?array en natif + @param array<int\|class-string<Exception>, callable(...)>.
Sequence[Depends] Depends[] (PHPDoc) + ?array (PHP).
Default(JSONResponse) Valeur par défaut directe JSONResponse::class (ou null avec vérification interne).
**extra: Any array $extra = [].
str \| None ?string (ou `string
 
Pour le type Lifespan[AppType]

Dans le constructeur, j'ai utilisé Lifespan<T> dans le PHPDoc, car T est lié à FastAPI. Cela permet aux outils d'analyse statique de comprendre que le Lifespan passé doit correspondre au type concret de l'application héritée.

Note importante : Contrairement à Python, PHP n'exécute pas de vérification des génériques à l'exécution. Toute cette richesse typée ne vit que dans les PHPDocs pour vos IDE (PHPStorm, VSCode avec Psalm) et les analyseurs statiques comme PHPStan ou Psalm. Le moteur PHP ne vérifie que les types scalaires natifs (bool, string, array, ?string, etc.) et les noms de classes/interfaces.