<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Adapter\Datastructures\UploadFileAdapter;
use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\Contracts;
use Eorbahapi\Dependency\DependencyInterface as LegacyDependencyInterface;
use Eorbahapi\Middlewares\MiddlewareInterface as LegacyMiddlewareInterface;
use Eorbahapi\Testing\TestClient;
use Eorbahapi\Testing\TestClientInterface as LegacyTestClientInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContractsTest extends TestCase
{
    #[Test]
    public function legacy_interfaces_extend_the_frozen_contracts(): void
    {
        $this->assertTrue(is_subclass_of(LegacyMiddlewareInterface::class, Contracts\MiddlewareInterface::class));
        $this->assertTrue(is_subclass_of(LegacyDependencyInterface::class, Contracts\DependencyInterface::class));
        $this->assertTrue(is_subclass_of(LegacyTestClientInterface::class, Contracts\TestClientInterface::class));
    }

    #[Test]
    public function the_current_test_client_satisfies_the_contract(): void
    {
        $reflection = new \ReflectionClass(TestClient::class);

        $this->assertTrue($reflection->implementsInterface(Contracts\TestClientInterface::class));
    }

    #[Test]
    public function adapters_implement_the_frozen_contracts(): void
    {
        $this->assertTrue(is_subclass_of(RequestAdapter::class, Contracts\RequestInterface::class));
        $this->assertTrue(is_subclass_of(ResponseAdapter::class, Contracts\ResponseInterface::class));
        $this->assertTrue(is_subclass_of(UploadFileAdapter::class, Contracts\UploadFileInterface::class));
    }

    #[Test]
    public function every_contract_class_is_psr4_aligned(): void
    {
        $directory = dirname(__DIR__) . '/src/Contracts';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $relative = substr($path, strlen($directory) + 1);
            $class = 'Eorbahapi\\Contracts\\' . str_replace('/', '\\', substr($relative, 0, -4));

            $this->assertTrue(class_exists($class) || interface_exists($class) || enum_exists($class), "Classe introuvable : $class");
            $reflection = new \ReflectionClass($class);
            $this->assertSame($path, $reflection->getFileName(), "Namespace et emplacement incohérents : $class");
        }
    }
}
