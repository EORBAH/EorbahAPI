<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Body;
use Eorbahapi\Request;
use Eorbahapi\RPC\JsonRPC;
use Eorbahapi\Testing\TestClient;
use Svinx\Validator\BaseModel;
use Svinx\Validator\Field;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Modèle de paramètres validé par Svinx.
 */
class RpcParams extends BaseModel
{
    #[Field(ge: 0)]
    public int $a = 0;

    #[Field(ge: 0)]
    public int $b = 0;
}

class JsonRpcTest extends TestCase
{
    private function app(): EorbahAPI
    {
        $rpc = new JsonRPC();
        $rpc->add_method('sum', fn (int $a, int $b) => $a + $b);
        $rpc->add_method('validated', fn (#[Body] RpcParams $params) => $params->a + $params->b);
        $rpc->add_method('describe', fn (Request $request) => $request->body('a'));
        $rpc->add_method('boom', function () {
            throw new \RuntimeException('échec');
        });

        $app = new EorbahAPI();
        $app->mount('/rpc', $rpc);

        return $app;
    }

    private function call(array $payload)
    {
        return (new TestClient($this->app()))->post('/rpc', ['json' => $payload]);
    }

    #[Test]
    public function it_executes_a_registered_method(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'method' => 'sum',
            'params' => ['a' => 1, 'b' => 2],
            'id' => 1,
        ]);

        $this->assertSame(200, $response->status());
        $this->assertSame([
            'jsonrpc' => '2.0',
            'result' => 3,
            'id' => 1,
        ], $response->json());
    }

    #[Test]
    public function an_unknown_method_returns_method_not_found(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'method' => 'nope', 'id' => 2]);

        $this->assertSame(-32601, $response->json()['error']['code']);
    }

    #[Test]
    public function an_invalid_request_returns_invalid_request(): void
    {
        $response = $this->call(['id' => 3]);

        $this->assertSame(-32600, $response->json()['error']['code']);
    }

    #[Test]
    public function a_wrong_jsonrpc_version_is_rejected(): void
    {
        $response = $this->call(['jsonrpc' => '1.0', 'method' => 'sum', 'id' => 4]);

        $this->assertSame(-32600, $response->json()['error']['code']);
    }

    #[Test]
    public function params_are_validated_by_a_svinx_model(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'method' => 'validated',
            'params' => ['a' => 4, 'b' => 6],
            'id' => 6,
        ]);

        $this->assertSame(10, $response->json()['result']);
    }

    #[Test]
    public function invalid_params_return_minus_32602(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'method' => 'validated',
            'params' => ['a' => -1],
            'id' => 7,
        ]);

        $this->assertSame(-32602, $response->json()['error']['code']);
        $this->assertStringStartsWith('Invalid params:', $response->json()['error']['message']);
    }

    #[Test]
    public function the_request_body_exposes_the_jsonrpc_params(): void
    {
        $response = $this->call([
            'jsonrpc' => '2.0',
            'method' => 'describe',
            'params' => ['a' => 42],
            'id' => 8,
        ]);

        $this->assertSame(42, $response->json()['result']);
    }

    #[Test]
    public function an_error_inside_a_method_returns_an_internal_error(): void
    {
        $response = $this->call(['jsonrpc' => '2.0', 'method' => 'boom', 'id' => 5]);

        $this->assertSame(-32603, $response->json()['error']['code']);
        $this->assertStringContainsString('échec', $response->json()['error']['message']);
    }
}
