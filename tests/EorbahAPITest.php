<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\BaseHTTPMiddleware;

class EorbahAPITest extends TestCase
{
    protected function setUp(): void
    {
        header_remove();
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
        })->middleware([BaseHTTPMiddleware::class]);

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
}
