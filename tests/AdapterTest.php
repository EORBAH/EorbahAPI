<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Adapter\Cache\FileCacheAdapter;
use Eorbahapi\Adapter\Cache\MemoryCacheAdapter;
use Eorbahapi\Adapter\Cache\RedisCacheAdapter;
use Eorbahapi\Adapter\Datastructures\UploadFileAdapter;
use Eorbahapi\Adapter\Http\RequestAdapter;
use Eorbahapi\Adapter\Http\ResponseAdapter;
use Eorbahapi\Adapter\Server\MinimalServerAdapter;
use Eorbahapi\Adapter\Server\SwooleServerAdapter;
use Eorbahapi\Contracts\CacheAdapterInterface;
use Eorbahapi\Contracts\ESGIAppInterface;
use Eorbahapi\Contracts\ResponseInterface;
use Eorbahapi\Contracts\ServerAdapterInterface;
use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Path;
use Eorbahapi\Request;
use Eorbahapi\Server\ESGIAppRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Adaptateur de serveur qui enregistre son invocation (test de ESGIAppRunner).
 */
class RecordingServerAdapter implements ServerAdapterInterface
{
    public ?ESGIAppInterface $app = null;
    public string $host = '';
    public int $port = 0;
    public bool $reload = false;

    public function run(ESGIAppInterface $app, string $host = '127.0.0.1', int $port = 8000, bool $reload = false): void
    {
        $this->app = $app;
        $this->host = $host;
        $this->port = $port;
        $this->reload = $reload;
    }
}

class AdapterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/eorbah_adapter_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    #[Test]
    public function the_request_adapter_exposes_the_concrete_request(): void
    {
        $request = new Request(
            body: ['nom' => 'Oumar'],
            params: ['id' => '7'],
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users/7?x=1'],
            query: ['x' => '1'],
            headers: ['Content-Type' => 'application/json'],
        );

        $adapter = new RequestAdapter($request);

        $this->assertSame('POST', $adapter->getMethod());
        $this->assertSame('/users/7?x=1', $adapter->getUri());
        $this->assertSame('/users/7', $adapter->getPath());
        $this->assertSame(['x' => '1'], $adapter->getQueryParams());
        $this->assertSame('application/json', $adapter->getHeader('content-type'));
        $this->assertSame(['nom' => 'Oumar'], $adapter->getBody());
        $this->assertSame('7', $adapter->getPathParams()['id']);
        $this->assertSame($request, $adapter->unwrap());
    }

    #[Test]
    public function request_attributes_are_immutable(): void
    {
        $adapter = new RequestAdapter(new Request(server: ['REQUEST_URI' => '/']));

        $withUser = $adapter->withAttribute('user', 'oumar');

        $this->assertNull($adapter->getAttribute('user'));
        $this->assertSame('oumar', $withUser->getAttribute('user'));
        $this->assertNotSame($adapter, $withUser);
    }

    #[Test]
    public function the_response_adapter_edits_status_headers_and_body(): void
    {
        $adapter = ResponseAdapter::create();

        $adapter->setStatus(201)->setBody(['created' => true])->setHeader('X-Trace', 'abc');

        $this->assertSame(201, $adapter->status());
        $this->assertSame(['created' => true], $adapter->body());
        $this->assertSame('abc', $adapter->header('x-trace'));
        $this->assertSame('{"created":true}', $adapter->text());

        $adapter->removeHeader('X-Trace');
        $this->assertNull($adapter->header('X-Trace'));
    }

    #[Test]
    public function the_application_adapter_drives_eorbahapi(): void
    {
        $app = new EorbahAPI('Test');
        $app->get('/hello/{name}', fn (#[Path] string $name) => ['hello' => $name]);

        $response = $app(
            new RequestAdapter(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/hello/oumar'])),
            ResponseAdapter::create()
        );

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(200, $response->status());
        $this->assertSame(['hello' => 'oumar'], json_decode($response->text(), true));
        $this->assertSame('application/json', $response->header('Content-Type'));
    }

    #[Test]
    public function the_cache_memory_adapter_behaves_like_the_contract(): void
    {
        $cache = new MemoryCacheAdapter();

        $this->assertInstanceOf(CacheAdapterInterface::class, $cache);
        $this->assertSame('memory', $cache->driver());
        $this->assertSame('valeur', $cache->remember('clé', fn () => 'valeur'));
        $this->assertTrue($cache->has('clé'));
        $cache->delete('clé');
        $this->assertFalse($cache->has('clé'));
    }

    #[Test]
    public function the_cache_file_adapter_persists_values(): void
    {
        $cache = new FileCacheAdapter($this->dir);

        $this->assertSame('file', $cache->driver());
        $this->assertTrue($cache->set('clé', ['a' => 1]));
        $this->assertSame(['a' => 1], (new FileCacheAdapter($this->dir))->get('clé'));
    }

    #[Test]
    public function the_cache_redis_adapter_exposes_its_driver(): void
    {
        $cache = new RedisCacheAdapter();

        $this->assertSame('redis', $cache->driver());
        $this->assertInstanceOf(CacheAdapterInterface::class, $cache);
    }

    #[Test]
    public function the_upload_file_adapter_exposes_a_stream(): void
    {
        $path = $this->dir . '/note.txt';
        file_put_contents($path, 'bonjour');

        $handle = fopen($path, 'rb');
        $adapter = new UploadFileAdapter(new UploadFile('note.txt', 'text/plain', $handle));

        $this->assertSame('note.txt', $adapter->getFilename());
        $this->assertSame('text/plain', $adapter->getContentType());
        $this->assertSame('bonjour', $adapter->read());
        $this->assertSame(0, $adapter->seek(0));
        $this->assertTrue($adapter->close());
    }

    #[Test]
    public function the_runner_uses_the_given_adapter(): void
    {
        $app = new EorbahAPI();
        $adapter = new RecordingServerAdapter();

        (new ESGIAppRunner())->run($app, '0.0.0.0', 9000, true, $adapter);

        $this->assertSame($app, $adapter->app);
        $this->assertSame('0.0.0.0', $adapter->host);
        $this->assertSame(9000, $adapter->port);
        $this->assertTrue($adapter->reload);
    }

    #[Test]
    public function the_runner_falls_back_to_a_default_adapter(): void
    {
        $app = new EorbahAPI();

        $this->assertInstanceOf(
            extension_loaded('swoole') ? SwooleServerAdapter::class : MinimalServerAdapter::class,
            ESGIAppRunner::defaultAdapter()
        );
    }

    #[Test]
    public function the_swoole_adapter_requires_the_extension(): void
    {
        if (extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole est installé.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('swoole');

        (new SwooleServerAdapter())->run(new EorbahAPI());
    }
}
