<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\RPC\JsonRPC;
use Eorbahapi\Params\Body;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;

/**
 * Paramètres de la méthode JSON-RPC.
 */
final class RpcSumParams extends BaseModel
{
    #[Field(ge: 0)]
    public int $a = 0;

    #[Field(ge: 0)]
    public int $b = 0;
}

/**
 * Serveur JSON-RPC 2.0 monté sous /rpc.
 */
final class RpcExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $rpc = new JsonRPC();

        // Une méthode JSON-RPC est un callable dont les paramètres sont résolus
        // par nom dans `params` (coercition, valeurs par défaut, injection de
        // Request/Response et de dépendances).
        $rpc->add_method('math.sum', fn (int $a, int $b) => $a + $b);
        $rpc->add_method('greet', fn (string $name = 'monde') => 'Bonjour ' . $name);

        // Les `params` deviennent le corps de la requête : un modèle Svinx (ou
        // tout attribut de paramètre) se comporte comme sur une route HTTP.
        $rpc->add_method('math.validated', fn (#[Body] RpcSumParams $params) => $params->a + $params->b);

        $app = new EorbahAPI();
        $app->mount('/rpc', $rpc);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('POST', '/rpc', ['json' => [
                'jsonrpc' => '2.0',
                'method' => 'math.sum',
                'params' => ['a' => 2, 'b' => 3],
                'id' => 1,
            ]], json: ['jsonrpc' => '2.0', 'result' => 5, 'id' => 1]),

            self::scenario('POST', '/rpc', ['json' => [
                'jsonrpc' => '2.0',
                'method' => 'greet',
                'params' => ['name' => 'Oumar'],
                'id' => 2,
            ]], json: ['jsonrpc' => '2.0', 'result' => 'Bonjour Oumar', 'id' => 2]),

            self::scenario('POST', '/rpc', ['json' => [
                'jsonrpc' => '2.0',
                'method' => 'math.validated',
                'params' => ['a' => 4, 'b' => 6],
                'id' => 4,
            ]], json: ['jsonrpc' => '2.0', 'result' => 10, 'id' => 4]),

            self::scenario('POST', '/rpc', ['json' => [
                'jsonrpc' => '2.0',
                'method' => 'math.validated',
                'params' => ['a' => -1],
                'id' => 5,
            ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32602, 'message' => "Invalid params: Le champ 'a' doit être >= 0"], 'id' => 5]),

            self::scenario('POST', '/rpc', ['json' => [
                'jsonrpc' => '2.0',
                'method' => 'inconnue',
                'id' => 3,
            ]], json: ['jsonrpc' => '2.0', 'error' => ['code' => -32601, 'message' => 'Method not found'], 'id' => 3]),
        ];
    }
}
