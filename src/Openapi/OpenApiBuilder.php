<?php

namespace Eorbahapi\Openapi;

use Eorbahapi\Dependency\DependencyResolver;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Openapi\Models\Parameter;
use Eorbahapi\Openapi\Models\RequestBody;
use Eorbahapi\Openapi\Models\SchemaRegistry;
use Eorbahapi\Openapi\Models\SecuritySchemes;
use Eorbahapi\Params\ParamTypes;
use Eorbahapi\Params\Security;

/**
 * Génère une spécification OpenAPI 3.1 à partir des routes de l'application.
 *
 * Aucune annotation OpenAPI n'est nécessaire : le générateur relit les routes
 * enregistrées, puis, pour chaque handler, les paramètres déclarés avec les
 * attributs de `Eorbahapi\Params`. Les types et contraintes viennent de
 * Svinx\Validator (`Type::fromReflection()->withConstraints()`) et les modèles
 * de `BaseModel::model_schema()` — la documentation reste donc synchronisée
 * avec la validation réelle.
 *
 *     $spec = (new OpenApiBuilder($app))->build();
 *
 * Options acceptées (toutes facultatives) :
 *
 *  - `title`, `description`, `version` : bloc `info` ;
 *  - `servers` : liste de serveurs OpenAPI ;
 *  - `operations` : métadonnées par opération, clé `"GET /users/{id}"`
 *    (`summary`, `description`, `tags`) ;
 *  - `components` : composants supplémentaires fusionnés dans la sortie ;
 *  - `securitySchemes` : définitions de sécurité supplémentaires (OAuth2...).
 */
final class OpenApiBuilder
{
    /** Méthodes HTTP documentées, dans l'ordre du routeur. */
    private const METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

    public function __construct(
        private readonly EorbahAPI $app,
        private readonly array $options = [],
    ) {
    }

    /**
     * Construit la spécification complète.
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $registry = new SchemaRegistry();
        $securitySchemes = new SecuritySchemes();
        $paths = [];
        $operationIds = [];

        foreach ($this->collectRoutes() as $route) {
            if ($this->isHidden($route['path'])) {
                continue;
            }

            $method = strtolower($route['method']);

            if (!in_array($method, self::METHODS, true)) {
                continue;
            }

            $path = $this->normalizeTemplate($route['path']);
            $paths[$path][$method] = $this->operation($route, $registry, $securitySchemes, $operationIds);
        }

        ksort($paths);

        foreach ($paths as &$operations) {
            ksort($operations);
        }
        unset($operations);

        $info = array_filter(
            [
                'title' => $this->options['title'] ?? ($this->app->title ?? Constants::DEFAULT_TITLE),
                'version' => $this->options['version'] ?? Constants::DEFAULT_VERSION,
                'summary' => $this->options['summary'] ?? null,
                'description' => $this->options['description'] ?? $this->app->description,
                'termsOfService' => $this->options['terms_of_service'] ?? null,
                'contact' => $this->options['contact'] ?? null,
                'license' => $this->options['license'] ?? null,
            ],
            static fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []
        );

        $spec = [
            'openapi' => Constants::OPENAPI_VERSION,
            'info' => $info,
            'paths' => $paths === [] ? new \stdClass() : $paths,
        ];

        if (isset($this->options['servers']) && $this->options['servers'] !== []) {
            $spec['servers'] = $this->options['servers'];
        }

        if (isset($this->options['tags']) && $this->options['tags'] !== []) {
            $spec['tags'] = $this->options['tags'];
        }

        if (isset($this->options['external_docs']) && $this->options['external_docs'] !== []) {
            $spec['externalDocs'] = $this->options['external_docs'];
        }

        $components = $this->components($registry, $securitySchemes);

        if ($components !== []) {
            $spec['components'] = $components;
        }

        return $spec;
    }

    /**
     * Empreinte des routes et options, utilisée comme clé de cache.
     *
     * Elle change dès qu'une route est ajoutée, retirée ou qu'un fichier source
     * d'un handler est modifié : le cache fichier ne sert donc jamais une
     * documentation périmée.
     */
    public function fingerprint(): string
    {
        $routes = [];

        foreach ($this->collectRoutes() as $route) {
            $callback = $route['callback'];
            $file = DependencyResolver::callbackFile($callback);

            $routes[] = [
                $route['method'],
                $route['path'],
                DependencyResolver::callbackKey($callback),
                $file !== null && is_file($file) ? filemtime($file) : null,
            ];
        }

        $payload = json_encode(
            [
                'app' => [
                    'title' => $this->app->title,
                    'description' => $this->app->description,
                ],
                'options' => $this->options,
                'routes' => $routes,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return hash('sha256', $payload);
    }

    /**
     * Toutes les routes visibles par le générateur, sous-applications comprises.
     *
     * @return list<array{method: string, path: string, callback: callable}>
     */
    public function collectRoutes(): array
    {
        $routes = [];
        $this->collectFromApp($this->app, '', $routes);

        return $routes;
    }

    // ------------------------------------------------------------------
    // Parcours des routes
    // ------------------------------------------------------------------

    /**
     * @param list<array{method: string, path: string, callback: callable}> $routes
     */
    private function collectFromApp(EorbahAPI $app, string $prefix, array &$routes): void
    {
        $hidden = $app->documentation_routes();

        foreach ($app->router->routes as $method => $routeConfigs) {
            foreach ($routeConfigs as $path => $config) {
                if (in_array((string) $path, $hidden, true)) {
                    continue;
                }

                $routes[] = [
                    'method' => strtoupper($method),
                    'path' => $this->joinPath($prefix, (string) $path),
                    'callback' => $config['callback'],
                ];
            }
        }

        foreach ($app->router->dynamicRoutes as $method => $configs) {
            foreach ($configs as $config) {
                if (in_array($config['route'], $hidden, true)) {
                    continue;
                }

                $routes[] = [
                    'method' => strtoupper($method),
                    'path' => $this->joinPath($prefix, $config['route']),
                    'callback' => $config['callback'],
                ];
            }
        }

        foreach ($app->mounted_apps() as $mountPrefix => $mounted) {
            if ($mounted instanceof EorbahAPI) {
                $this->collectFromApp($mounted, $this->joinPath($prefix, (string) $mountPrefix), $routes);
            }
        }
    }

    private function joinPath(string $prefix, string $path): string
    {
        $prefix = rtrim($prefix, '/');
        $path = $path === '' ? '/' : $path;

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return ($prefix . $path) === '' ? '/' : $prefix . $path;
    }

    private function isHidden(string $path): bool
    {
        return in_array($path, $this->app->documentation_routes(), true);
    }

    // ------------------------------------------------------------------
    // Opérations
    // ------------------------------------------------------------------

    /**
     * @param array{method: string, path: string, callback: callable} $route
     * @param array<string, true> $operationIds
     * @return array<string, mixed>
     */
    private function operation(
        array $route,
        SchemaRegistry $registry,
        SecuritySchemes $securitySchemes,
        array &$operationIds
    ): array {
        $descriptors = DependencyResolver::describe($route['callback']);

        $parameters = [];
        $requestBody = null;
        $security = [];

        foreach ($descriptors as $descriptor) {
            $param = $descriptor->param;

            if ($param === null) {
                continue;
            }

            switch ($param->location()) {
                case ParamTypes::BODY:
                    $requestBody = (new RequestBody($descriptor, 'application/json', $registry))->toArray();
                    break;

                case ParamTypes::FORM:
                    $requestBody = (new RequestBody($descriptor, 'application/x-www-form-urlencoded', $registry))->toArray();
                    break;

                case ParamTypes::FILE:
                    $requestBody = (new RequestBody($descriptor, 'multipart/form-data', $registry))->toArray();
                    break;

                case ParamTypes::SECURITY:
                    $requirement = $securitySchemes->requirement(
                        $param instanceof Security ? ($param->dependency ?? $descriptor->typeName) : $descriptor->typeName
                    );

                    if ($requirement !== null) {
                        $security[] = $requirement;
                    }
                    break;

                default:
                    $parameter = (new Parameter($descriptor, $registry))->toArray();

                    if ($parameter !== []) {
                        $parameters[] = $parameter;
                    }
            }
        }

        // Un placeholder `{id}` de l'URL doit apparaître en `in: path`, même
        // lorsqu'il n'est pas déclaré avec #[Path].
        $declared = array_column($parameters, 'name');

        foreach ($this->placeholders($route['path']) as $placeholder) {
            if (!in_array($placeholder, $declared, true)) {
                $parameters[] = [
                    'name' => $placeholder,
                    'in' => 'path',
                    'required' => true,
                    'schema' => ['type' => 'string'],
                ];
            }
        }

        $operation = [
            'operationId' => $this->operationId($route, $operationIds),
            'responses' => [
                '200' => [
                    'description' => 'Réponse réussie',
                    'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                ],
                '422' => [
                    'description' => 'Erreur de validation',
                    'content' => [
                        'application/json' => [
                            'schema' => ['$ref' => Constants::REF_PREFIX . Constants::VALIDATION_ERROR_SCHEMA],
                        ],
                    ],
                ],
            ],
        ];

        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }

        if ($requestBody !== null) {
            $operation['requestBody'] = $requestBody;
        }

        if ($security !== []) {
            $operation['security'] = $security;
        }

        $metadata = $this->options['operations'][$route['method'] . ' ' . $route['path']] ?? [];

        if (isset($metadata['summary'])) {
            $operation['summary'] = $metadata['summary'];
        }
        if (isset($metadata['description'])) {
            $operation['description'] = $metadata['description'];
        }
        if (isset($metadata['tags'])) {
            $operation['tags'] = (array) $metadata['tags'];
        }

        return $operation;
    }

    /**
     * @param array{method: string, path: string, callback: callable} $route
     * @param array<string, true> $operationIds
     */
    private function operationId(array $route, array &$operationIds): string
    {
        $callback = $route['callback'];

        $candidate = is_array($callback)
            ? (string) $callback[1]
            : strtolower($route['method']) . preg_replace('/[^a-zA-Z0-9]+/', '_', trim($route['path'], '/'));

        $candidate = trim((string) $candidate, '_');

        if ($candidate === '') {
            $candidate = 'root';
        }

        $unique = $candidate;
        $suffix = 2;

        while (isset($operationIds[$unique])) {
            $unique = $candidate . '_' . $suffix++;
        }

        $operationIds[$unique] = true;

        return $unique;
    }

    /**
     * @return list<string>
     */
    private function placeholders(string $path): array
    {
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]+)?\}/', $path, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * Normalise un gabarit de route : `{id:int}` et `{chemin:path}` -> `{id}`,
     * `{chemin}`.
     */
    private function normalizeTemplate(string $path): string
    {
        return (string) preg_replace('/\{([A-Za-z_][A-Za-z0-9_]*)(?::[^}]+)?\}/', '{$1}', $path);
    }

    // ------------------------------------------------------------------
    // Composants
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private function components(SchemaRegistry $registry, SecuritySchemes $securitySchemes): array
    {
        $components = [];

        foreach ([$registry->components(), $securitySchemes->components()] as $block) {
            foreach ($block as $section => $entries) {
                $components[$section] = array_merge($components[$section] ?? [], $entries);
            }
        }

        if (isset($this->options['securitySchemes']) && is_array($this->options['securitySchemes'])) {
            $components['securitySchemes'] = array_merge(
                $components['securitySchemes'] ?? [],
                $this->options['securitySchemes']
            );
        }

        if (isset($this->options['components']) && is_array($this->options['components'])) {
            foreach ($this->options['components'] as $section => $entries) {
                $components[$section] = array_merge($components[$section] ?? [], (array) $entries);
            }
        }

        return $components;
    }
}
