<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\ExceptionHandlers;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Exceptions\ValidationException;

class ExceptionHandlersTest extends TestCase
{
    protected function setUp(): void
    {
        header_remove();
        $_ENV['APP_DEBUG'] = '0';
    }

    public function testHttpExceptionHandlerReturnsJsonResponse(): void
    {
        $handler = new ExceptionHandlers();
        $request = new Request();
        $response = new Response();

        ob_start();
        $result = $handler->httpExceptionHandler(new HTTPException(404, 'Not found'), $request, $response);
        $output = ob_get_clean();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('"status":404', $output);
        $this->assertStringContainsString('"message":"Not found"', $output);
    }

    public function testRequestValidationExceptionHandlerReturnsDetails(): void
    {
        $handler = new ExceptionHandlers();
        $request = new Request();
        $response = new Response();
        $exception = new ValidationException(['name' => 'required']);

        ob_start();
        $result = $handler->requestValidationExceptionHandler($exception, $request, $response);
        $output = ob_get_clean();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('"status":422', $output);
        $this->assertStringContainsString('"details":{"name":"required"}', $output);
    }

    public function testGenericExceptionHandlerIncludesDebugInOutputWhenDebugEnabled(): void
    {
        $_ENV['APP_DEBUG'] = '1';

        $handler = new ExceptionHandlers();
        $request = new Request();
        $response = new Response();

        ob_start();
        $result = $handler->genericExceptionHandler(new \RuntimeException('boom'), $request, $response);
        $output = ob_get_clean();

        $this->assertInstanceOf(Response::class, $result);
        $this->assertStringContainsString('"status":500', $output);
        $this->assertStringContainsString('"debug":"boom"', $output);
    }
}
