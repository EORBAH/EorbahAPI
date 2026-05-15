<?php
namespace EorBah545\Eorbahapi\mcp;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;

class MCPProtocolHandler
{
    private MCPToolRegistry $registry;
    private MCPToolExecutor $executor;
    private string $serverName;
    private string $serverDescription;

    public function __construct(EorbahApiMCP $mcp, array $options)
    {
        $this->serverName = $options['name'];
        $this->serverDescription = $options['description'];
        $this->registry = new MCPToolRegistry($mcp, $options);
        $this->executor = new MCPToolExecutor($mcp);
    }

    public function handle(Request $request, Response $response): void
    {
        // S'assurer que l'exécuteur a les bons objets
        $this->executor->setRequestResponse($request, $response);

        $rawBody = file_get_contents('php://input');
        $json = json_decode($rawBody, true);
        if (!$json || !isset($json['jsonrpc']) || $json['jsonrpc'] !== '2.0') {
            $response->status(400)->json(['error' => 'Invalid JSON-RPC']);
            return;
        }

        $id = $json['id'] ?? null;
        $method = $json['method'] ?? '';
        $params = $json['params'] ?? [];

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($params),
                'tools/list' => $this->listTools(),
                'tools/call' => $this->callTool($params),
                default => ['error' => ['code' => -32601, 'message' => 'Method not found']],
            };

            if (isset($result['error'])) {
                $response->json(['jsonrpc' => '2.0', 'id' => $id, 'error' => $result['error']]);
            } else {
                $response->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
            }
        } catch (\Exception $e) {
            $response->json([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32000, 'message' => $e->getMessage()],
            ]);
        }
    }

    private function initialize(array $params): array
    {
        return [
            'protocolVersion' => '0.1.0',
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => $this->serverName,
                'version' => '1.0.0',
            ],
        ];
    }

    private function listTools(): array
    {
        return ['tools' => $this->registry->listTools()];
    }

    private function callTool(array $params): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];
        if (empty($toolName)) {
            throw new \Exception('Missing tool name');
        }
        $rawResult = $this->executor->execute($toolName, $arguments);
        // Convertir le résultat en texte (ou JSON)
        $text = is_scalar($rawResult) ? (string)$rawResult : json_encode($rawResult, JSON_PRETTY_PRINT);
        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
        ];
    }
}