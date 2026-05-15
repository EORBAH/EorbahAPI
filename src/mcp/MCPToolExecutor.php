<?php
namespace EorBah545\Eorbahapi\mcp;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

class MCPToolExecutor
{
    private EorbahApiMCP $mcp;
    private ?Request $request = null;
    private ?Response $response = null;

    public function __construct(EorbahApiMCP $mcp)
    {
        $this->mcp = $mcp;
    }

    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * Exécute un outil MCP.
     * @param string $toolName Operation ID
     * @param array $arguments Arguments fournis par l'IA
     * @return mixed Résultat (sera formaté en texte/JSON)
     */
    public function execute(string $toolName, array $arguments): mixed
    {
        // Trouver la route correspondant à $toolName
        $route = $this->findRouteByOperationId($toolName);
        if (!$route) {
            throw new \Exception("Tool '$toolName' not found");
        }

        // Préparer les arguments pour le callback
        $prepared = $this->prepareArguments($route, $arguments);

        // Injecter les objets Request/Response dans l'exécuteur
        $this->injectRequestResponse();

        // Appeler le callback via la méthode dédiée de EorbahApiMCP
        return $this->mcp->executeCallbackWithArgs($route['callback'], $prepared);
    }

    private function findRouteByOperationId(string $operationId): ?array
    {
        $routes = $this->mcp->getInternalRoutes();
        foreach ($routes as $route) {
            $computedId = $this->computeOperationId($route);
            if ($computedId === $operationId) {
                return $route;
            }
        }
        return null;
    }

    private function computeOperationId(array $route): string
    {
        $method = $route['method'];
        $uri = $route['uri'];
        $cleanUri = str_replace(['{', '}', ':'], '_', trim($uri, '/'));
        if ($cleanUri === '') {
            $cleanUri = 'root';
        }
        return $method . '_' . $cleanUri;
    }

    private function prepareArguments(array $route, array $arguments): array
    {
        $prepared = [];
        // Paramètres de chemin
        if ($route['isDynamic'] && isset($route['paramNames'])) {
            foreach ($route['paramNames'] as $name) {
                if (isset($arguments[$name])) {
                    $prepared[$name] = $arguments[$name];
                }
            }
        }
        // Corps de la requête
        if (isset($arguments['_body'])) {
            $prepared['_body'] = $arguments['_body'];
        }
        // On peut aussi passer les autres arguments (pour query string, etc.)
        foreach ($arguments as $key => $value) {
            if (!in_array($key, $route['paramNames'] ?? []) && $key !== '_body') {
                $prepared[$key] = $value;
            }
        }
        return $prepared;
    }

    private function injectRequestResponse(): void
    {
        // Si les objets Request/Response ne sont pas définis, on en crée
        if (!$this->request) {
            $this->request = new Request();
        }
        if (!$this->response) {
            $this->response = new Response();
        }
        // On les communique à l'instance MCP pour qu'ils soient utilisés lors de l'exécution
        $this->mcp->setRequestResponse($this->request, $this->response);
    }
}