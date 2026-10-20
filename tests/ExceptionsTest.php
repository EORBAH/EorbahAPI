<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExceptionsTest extends TestCase
{
    #[Test]
    public function an_http_exception_is_rendered_as_json(): void
    {
        $app = new EorbahAPI();
        $app->get('/forbidden', function () {
            throw new HTTPException(403, 'Accès refusé');
        });

        $response = (new TestClient($app))->get('/forbidden');

        $this->assertSame(403, $response->status());
        $this->assertSame(['error' => true, 'status' => 403, 'message' => 'Accès refusé'], $response->json());
    }

    #[Test]
    public function an_http_exception_keeps_its_headers(): void
    {
        $app = new EorbahAPI();
        $app->get('/challenge', function () {
            throw new HTTPException(401, 'Unauthorized', ['WWW-Authenticate' => 'Bearer']);
        });

        $response = (new TestClient($app))->get('/challenge');

        $this->assertSame(401, $response->status());
        $this->assertSame('Bearer', $response->header('WWW-Authenticate'));
    }

    #[Test]
    public function a_validation_exception_is_rendered_as_422_with_details(): void
    {
        $app = new EorbahAPI();
        $app->post('/validate', function () {
            throw new ValidationException(
                [['loc' => ['body', 'name'], 'type' => 'missing', 'msg' => 'Le champ est requis.']],
                'Request validation error'
            );
        });

        $response = (new TestClient($app))->post('/validate', ['json' => []]);

        $this->assertSame(422, $response->status());
        $this->assertSame('Request validation error', $response->json()['message']);
        $this->assertSame('body', $response->json()['details'][0]['loc'][0]);
    }

    #[Test]
    public function an_unexpected_error_is_a_500_without_debug_information(): void
    {
        $app = new EorbahAPI();
        $app->get('/boom', function () {
            throw new \RuntimeException('détail interne');
        });

        $response = (new TestClient($app))->get('/boom');

        $this->assertSame(500, $response->status());
        $this->assertNull($response->json()['debug']);
    }

    #[Test]
    public function debug_mode_exposes_the_error_message(): void
    {
        $app = new EorbahAPI(debug: true);
        $app->get('/boom', function () {
            throw new \RuntimeException('détail interne');
        });

        $response = (new TestClient($app))->get('/boom');

        $this->assertSame(500, $response->status());
        $this->assertSame('détail interne', $response->json()['debug']);
    }

    #[Test]
    public function a_custom_handler_can_replace_the_default_one(): void
    {
        $app = new EorbahAPI();
        $app->setExceptionHandler(\RuntimeException::class, fn (\Throwable $e) => new JSONResponse(
            ['custom' => $e->getMessage()],
            418
        ));
        $app->get('/boom', function () {
            throw new \RuntimeException('théière');
        });

        $response = (new TestClient($app))->get('/boom');

        $this->assertSame(418, $response->status());
        $this->assertSame(['custom' => 'théière'], $response->json());
    }
}
