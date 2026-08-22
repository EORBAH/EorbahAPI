<?php

namespace Eorbahapi\rpc;

use Eorbahapi\DependencyResolver;
use Eorbahapi\BaseMountedApplication;
use function Eorbahapi\Responses\JSONResponse;

class JsonRPC extends BaseMountedApplication
{
    private array $methods = [];
    private array $options;
    private DependencyResolver $resolver;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'name' => 'JSON-RPC Server',
            'description' => 'JSON-RPC server exposing local routes as tools',
        ], $options);

        $this->resolver = new DependencyResolver($this->request, $this->response);
    }


    /**
     * Point d'entrée principal appelé par mount().
     */
    public function process(): void
    {
        $data = $this->request->body();

        // Vérifications de base de la requête JSON‑RPC
        if (!is_array($data) || !isset($data['method'])) {
            $this->sendError(null, -32600, 'Invalid Request');
            return;
        }

        $id = $data['id'] ?? null;
        $method = $data['method'];
        $params = $data['params'] ?? [];

        // La version doit être "2.0" (optionnel, mais recommandé)
        if (isset($data['jsonrpc']) && $data['jsonrpc'] !== '2.0') {
            $this->sendError($id, -32600, 'Invalid Request: jsonrpc must be "2.0"');
            return;
        }

        // Vérifier que la méthode existe
        if (!isset($this->methods[$method])) {
            $this->sendError($id, -32601, 'Method not found');
            return;
        }

        $callback = $this->methods[$method];

        try {
            // Le resolver construit les arguments en fonction des paramètres nommés
            // et des dépendances (type‑hints) de la fonction.
            $args = $this->resolver->resolve($callback, $params);
            $result = $callback(...$args);

            // Envoi de la réponse de succès
            echo JSONResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $id,
            ]);
        } catch (\Throwable $e) {
            // Erreur interne du serveur ou lors de l'exécution
            $this->sendError($id, -32603, 'Internal error: ' . $e->getMessage());
        }
    }

    /**
     * Envoie une réponse d'erreur JSON‑RPC.
     */
    private function sendError($id, int $code, string $message): void {
        echo JSONResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ]);
    }

    /**
     * Enregistre une méthode JSON‑RPC.
     *
     * @param string   $method   Nom de la méthode (ex: "user.create")
     * @param callable $callback Fonction ou méthode à appeler.
     */
    public function add_method(string $method, callable $callback): void
    {
        $this->methods[$method] = $callback;
    }
}