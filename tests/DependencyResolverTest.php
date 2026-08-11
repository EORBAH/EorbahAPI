<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\DependencyResolver;
use Eorbahapi\Request;
use Eorbahapi\Response;

class DependencyResolverTest extends TestCase
{
    public function testResolvesRequestAndResponse(): void
    {
        $resolver = new DependencyResolver(new Request(), new Response());
        $args = $resolver->resolve(function (Request $request, Response $response) {
            return null;
        });

        $this->assertInstanceOf(Request::class, $args[0]);
        $this->assertInstanceOf(Response::class, $args[1]);
    }

    public function testResolvesProvidedParameters(): void
    {
        $resolver = new DependencyResolver(new Request(), new Response());
        $args = $resolver->resolve(function (int $id, string $name = 'default') {
            return null;
        }, ['id' => 12]);

        $this->assertSame(12, $args[0]);
        $this->assertSame('default', $args[1]);
    }

    public function testResolvesServiceFromContainer(): void
    {
        $resolver = new DependencyResolver(new Request(), new Response());
        $service = new ServiceExample();
        $resolver->set(ServiceExample::class, $service);

        $args = $resolver->resolve(function (ServiceExample $serviceExample) {
            return null;
        });

        $this->assertSame($service, $args[0]);
    }

    public function testAutowiresClassWithoutConstructor(): void
    {
        $resolver = new DependencyResolver(new Request(), new Response());
        $args = $resolver->resolve(function (DummyAuto $dummy) {
            return null;
        });

        $this->assertInstanceOf(DummyAuto::class, $args[0]);
    }
}

class DummyAuto
{
}

class ServiceExample
{
}
