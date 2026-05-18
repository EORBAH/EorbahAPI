<?php

namespace EorBah545\Eorbahapi\rpc;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\DependencyResolver;

class JsonRPC
{
    private Request $request;
    private Response $response;
    private array $methods = [];
    private array $options;
    private DependencyResolver $resolver;

    public function __construct(array $options = [])
    {
        $this->options = array_merge([
            'name'        => 'JSON-RPC Server',
            'description' => 'JSON-RPC server exposing local routes as tools',
        ], $options);

        $this->request  = new Request();
        $this->response = new Response();
        $this->resolver = new DependencyResolver($this->request, $this->response);
    }

    /**
     * Permet à EorbahAPI d'injecter les instances Request/Response partagées.
     */
    public function setRequestResponse(Request $request, Response $response): void
    {
        $this->request  = $request;
        $this->response = $response;
    }

    /**
     * Point d'entrée principal appelé par mount().
     *
     * @param Request  $request
     * @param Response $response
     */
    public function handle(Request $request, Response $response): void
    {
        $this->setRequestResponse($request, $response);
        $data = $this->request->body();

        // Vérifications de base de la requête JSON‑RPC
        if (!is_array($data) || !isset($data['method'])) {
            $this->sendError(null, -32600, 'Invalid Request');
            return;
        }

        $id     = $data['id'] ?? null;
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
            $this->response->json([
                'jsonrpc' => '2.0',
                'result'  => $result,
                'id'      => $id,
            ]);
        } catch (\Throwable $e) {
            // Erreur interne du serveur ou lors de l'exécution
            $this->sendError($id, -32603, 'Internal error: ' . $e->getMessage());
        }
    }

    /**
     * Envoie une réponse d'erreur JSON‑RPC.
     */
    private function sendError($id, int $code, string $message): void
    {
        $this->response->json([
            'jsonrpc' => '2.0',
            'error'   => [
                'code'    => $code,
                'message' => $message,
            ],
            'id'      => $id,
        ]);
    }

    /**
     * Compatibilité avec la signature run($http, $handler) de EorbahAPI.
     */
    public function run($http = "404", $handler = null): void
    {
        $this->handle($this->request, $this->response);
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