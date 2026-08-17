<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\BaseHTTPMiddleware;
use function Eorbahapi\Responses\JSONResponse;

class EorbahAPITest extends TestCase
{
    protected function setUp(): void
    {
        header_remove();
        http_response_code(200); // réinitialisation du code de réponse
    }

    public function testRegisterRouteStoresRoute(): void
    {
        $app = new EorbahAPI();
        $app->get('/hello', function () {
            return 'ok';
        });

        $property = new \ReflectionProperty(EorbahAPI::class, 'routes');
        $property->setAccessible(true);
        $routes = $property->getValue($app);

        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/hello', $routes['GET']);
    }

    public function testMiddlewareAddsToLastRoute(): void
    {
        $app = new EorbahAPI();
        $app->get('/secure', function () {
            return 'ok';
        }, [BaseHTTPMiddleware::class]);

        $property = new \ReflectionProperty(EorbahAPI::class, 'routes');
        $property->setAccessible(true);
        $routes = $property->getValue($app);

        $this->assertIsArray($routes['GET']['/secure']);
        $this->assertArrayHasKey('middlewares', $routes['GET']['/secure']);
        $this->assertSame(BaseHTTPMiddleware::class, $routes['GET']['/secure']['middlewares'][0]['class']);
    }

    public function testAddGlobalMiddlewareStoresConfig(): void
    {
        $app = new EorbahAPI();
        $app->addMiddleware(BaseHTTPMiddleware::class, ['test' => true]);

        $property = new \ReflectionProperty(EorbahAPI::class, 'globalMiddlewares');
        $property->setAccessible(true);
        $middlewares = $property->getValue($app);

        $this->assertCount(1, $middlewares);
        $this->assertSame(BaseHTTPMiddleware::class, $middlewares[0]['class']);
        $this->assertSame(['test' => true], $middlewares[0]['options']);
    }

    public function testJsonResponseHelperCanBeReturnedFromRoute(): void
    {
        $app = new EorbahAPI();
        $method = new \ReflectionMethod(EorbahAPI::class, 'applyReturn');
        $method->setAccessible(true);

        ob_start();
        $method->invoke($app, function () {
            return JSONResponse(['hello' => 'world'], 201, ['X-Test' => 'done']);
        });
        $output = ob_get_clean();

        // Vérification du contenu JSON
        $this->assertSame(['hello' => 'world'], json_decode(trim($output), true));

        // Vérification du code de réponse (doit être défini par JSONResponse)
        $this->assertSame(201, http_response_code());

        // Si vous voulez aussi vérifier l'en-tête X-Test (optionnel)
        // $this->assertSame('done', xdebug_get_headers()['X-Test'] ?? null);
    }
}