<?php

namespace Eorbahapi\RPC;

use Eorbahapi\BaseMountedApplication;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Dependency\DependencyResolver;
use Eorbahapi\Exceptions\ValidationException;

/**
 * Serveur JSON-RPC 2.0 montable.
 *
 *     $rpc = new JsonRPC();
 *     $rpc->add_method('user.create', fn (#[Body] CreateUser $user) => ...);
 *     $app->mount('/rpc', $rpc);
 *
 * Le traitement remplit la réponse partagée (`process()`) : le pipeline
 * d'EorbahAPI décide de l'envoi, ce qui rend le serveur testable via TestClient.
 */
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

        $this->resolver = new DependencyResolver($this->request ?? new Request(), $this->response ?? new Response());
    }

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

        // Les `params` de l'enveloppe deviennent le corps de la requête : les
        // attributs de paramètre (#[Body], #[Query]...) fonctionnent comme dans
        // une route, et `Request::body()` expose les paramètres de la méthode.
        $rpcRequest = $this->request->withBody(is_array($params) ? $params : []);
        $this->resolver->setRequestResponse($rpcRequest, $this->response);

        try {
            // Le resolver construit les arguments en fonction des paramètres nommés
            // et des dépendances (type‑hints) de la fonction.
            $args = $this->resolver->resolve($callback, is_array($params) ? $params : []);
            $result = $callback(...$args);

            // Réponse de succès
            $this->response = new JSONResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $id,
            ]);
        } catch (ValidationException $e) {
            // Paramètres invalides : -32602 (Invalid params), avec le détail.
            $detail = $e->getErrors()[0]['msg'] ?? $e->getMessage();
            $this->sendError($id, -32602, 'Invalid params: ' . $detail);
        } catch (\Throwable $e) {
            // Erreur interne du serveur ou lors de l'exécution
            $this->sendError($id, -32603, 'Internal error: ' . $e->getMessage());
        }
    }

    /**
     * Remplit la réponse avec une erreur JSON‑RPC.
     */
    private function sendError($id, int $code, string $message): void
    {
        $this->response = new JSONResponse([
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
     * @param string   $method   Nom de la méthode (ex: "user.create").
     * @param callable $callback Fonction ou méthode à appeler.
     */
    public function add_method(string $method, callable $callback): void
    {
        $this->methods[$method] = $callback;
    }
}
