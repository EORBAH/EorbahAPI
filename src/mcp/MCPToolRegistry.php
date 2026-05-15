<?php
namespace EorBah545\Eorbahapi\mcp;

class MCPToolRegistry
{
    private EorbahApiMCP $mcp;
    private array $options;
    private array $toolsCache = [];

    public function __construct(EorbahApiMCP $mcp, array $options)
    {
        $this->mcp = $mcp;
        $this->options = $options;
    }

    public function listTools(): array
    {
        if (!empty($this->toolsCache)) {
            return $this->toolsCache;
        }

        $tools = [];
        $routes = $this->mcp->getInternalRoutes();

        foreach ($routes as $route) {
            $operationId = $this->computeOperationId($route);
            if (!$this->passesFilters($operationId)) {
                continue;
            }

            $inputSchema = $this->buildInputSchema($route);
            $description = $this->getRouteDescription($route);

            $tools[] = [
                'name' => $operationId,
                'description' => $description,
                'inputSchema' => $inputSchema,
            ];
        }

        $this->toolsCache = $tools;
        return $tools;
    }

    private function computeOperationId(array $route): string
    {
        $method = $route['method'];
        $uri = $route['uri'];
        // Nettoie l'URI pour en faire un identifiant valide
        $cleanUri = str_replace(['{', '}', ':'], '_', trim($uri, '/'));
        if ($cleanUri === '') {
            $cleanUri = 'root';
        }
        return $method . '_' . $cleanUri;
    }

    private function passesFilters(string $operationId): bool
    {
        $include = $this->options['include_operations'] ?? [];
        $exclude = $this->options['exclude_operations'] ?? [];

        if (!empty($include) && !in_array($operationId, $include)) {
            return false;
        }
        if (!empty($exclude) && in_array($operationId, $exclude)) {
            return false;
        }
        // Le filtrage par tags n'est pas implémenté ici (pas de tags dans les routes)
        return true;
    }

    private function buildInputSchema(array $route): array
    {
        $properties = [];
        $required = [];

        // Paramètres de chemin (pour routes dynamiques)
        if ($route['isDynamic'] && isset($route['paramNames'])) {
            foreach ($route['paramNames'] as $name) {
                $properties[$name] = ['type' => 'string', 'description' => "Path parameter $name"];
                $required[] = $name;
            }
        }

        // Pour POST/PUT, ajouter un paramètre body (objet)
        if (in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
            $properties['_body'] = [
                'type' => 'object',
                'description' => 'Request body (JSON)',
            ];
            // body n'est pas requis par défaut
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    private function getRouteDescription(array $route): string
    {
        // Essayer d'extraire un commentaire du callback
        $callback = $route['callback'];
        try {
            $ref = new \ReflectionFunction($callback);
            $doc = $ref->getDocComment();
            if ($doc && preg_match('/@description\s+(.+)/', $doc, $match)) {
                return $match[1];
            }
            if ($doc && preg_match('/\/\*\*\s*\n\s*\*\s*(.+)/', $doc, $match)) {
                return trim($match[1]);
            }
        } catch (\ReflectionException $e) {
        }
        return "Route {$route['method']} {$route['uri']}";
    }
}