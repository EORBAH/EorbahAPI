<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Request;
use Eorbahapi\Responses\FileResponse;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FileResponseTest extends TestCase
{
    private string $dir;
    private string $path;
    private const CONTENT = 'bonjour le monde';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/eorbah_file_' . uniqid('', true);
        mkdir($this->dir, 0777, true);
        $this->path = $this->dir . '/note.txt';
        file_put_contents($this->path, self::CONTENT);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
    }

    private function request(string $method = 'GET', array $headers = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/fichier'],
            headers: $headers,
        );
    }

    #[Test]
    public function a_file_response_exposes_the_expected_headers(): void
    {
        $response = new FileResponse($this->path);

        $this->assertSame(200, $response->status());
        $this->assertSame('text/plain; charset=UTF-8', $response->getHeader('Content-Type'));
        $this->assertSame((string) strlen(self::CONTENT), $response->getHeader('Content-Length'));
        $this->assertSame('bytes', $response->getHeader('Accept-Ranges'));
        $this->assertMatchesRegularExpression('/^"[^"]+"$/', (string) $response->etag());
        $this->assertNotNull($response->lastModified());
        $this->assertNotNull($response->getHeader('Last-Modified'));
        $this->assertSame(self::CONTENT, $response->contents());
    }

    #[Test]
    public function a_range_request_returns_206_and_the_slice(): void
    {
        $response = new FileResponse(
            $this->path,
            request: $this->request('GET', ['Range' => 'bytes=0-6']),
        );

        $this->assertSame(206, $response->status());
        $this->assertSame('bytes 0-6/' . strlen(self::CONTENT), $response->getHeader('Content-Range'));
        $this->assertSame('7', $response->getHeader('Content-Length'));
        $this->assertSame('bonjour', $response->contents());
    }

    #[Test]
    public function a_suffix_range_returns_the_last_bytes(): void
    {
        $response = new FileResponse(
            $this->path,
            request: $this->request('GET', ['Range' => 'bytes=-5']),
        );

        $this->assertSame(206, $response->status());
        $this->assertSame('monde', $response->contents());
    }

    #[Test]
    public function an_unsatisfiable_range_returns_416(): void
    {
        $response = new FileResponse(
            $this->path,
            request: $this->request('GET', ['Range' => 'bytes=999-']),
        );

        $this->assertSame(416, $response->status());
        $this->assertSame('bytes */' . strlen(self::CONTENT), $response->getHeader('Content-Range'));
        $this->assertSame('0', $response->getHeader('Content-Length'));
        $this->assertSame('', $response->contents());
    }

    #[Test]
    public function a_matching_etag_returns_304(): void
    {
        $base = new FileResponse($this->path);
        $etag = (string) $base->etag();

        $response = new FileResponse(
            $this->path,
            request: $this->request('GET', ['If-None-Match' => $etag]),
        );

        $this->assertSame(304, $response->status());
        $this->assertNull($response->getHeader('Content-Length'));
        $this->assertSame('', $response->contents());
    }

    #[Test]
    public function a_recent_if_modified_since_returns_304(): void
    {
        $base = new FileResponse($this->path);
        $date = gmdate('D, d M Y H:i:s \G\M\T', ((int) $base->lastModified()) + 60);

        $response = new FileResponse(
            $this->path,
            request: $this->request('GET', ['If-Modified-Since' => $date]),
        );

        $this->assertSame(304, $response->status());
    }

    #[Test]
    public function a_head_request_sends_no_body(): void
    {
        $response = new FileResponse($this->path, request: $this->request('HEAD'));

        $this->assertTrue($response->isHeadRequest());
        $this->expectOutputString('');

        $response->send();
    }

    #[Test]
    public function send_outputs_the_file_content(): void
    {
        $response = new FileResponse($this->path);

        $this->expectOutputString(self::CONTENT);

        $response->send();
    }

    #[Test]
    public function download_sets_an_attachment_disposition(): void
    {
        $response = FileResponse::download($this->path, 'rapport.txt');

        $this->assertSame('attachment; filename="rapport.txt"', $response->getHeader('Content-Disposition'));
    }

    #[Test]
    public function an_upload_file_can_be_served(): void
    {
        $handle = fopen($this->path, 'rb');
        $upload = new UploadFile('note.txt', 'text/plain', $handle);

        $response = FileResponse::fromUploadFile($upload);

        $this->assertSame('text/plain', $response->getHeader('Content-Type'));
        $this->assertSame(self::CONTENT, $response->contents());
        $this->assertSame('inline; filename="note.txt"', $response->getHeader('Content-Disposition'));
    }

    #[Test]
    public function eorbahapi_attaches_the_request_automatically(): void
    {
        $app = new EorbahAPI();
        $app->get('/fichier', fn () => new FileResponse($this->path));

        $client = new TestClient($app);

        $full = $client->get('/fichier');
        $this->assertSame(200, $full->status());
        $this->assertSame('text/plain; charset=UTF-8', $full->header('Content-Type'));

        $range = $client->get('/fichier', ['headers' => ['Range' => 'bytes=8-9']]);
        $this->assertSame(206, $range->status());
        $this->assertSame('bytes 8-9/' . strlen(self::CONTENT), $range->header('Content-Range'));

        $etag = (string) $full->header('ETag');
        $notModified = $client->get('/fichier', ['headers' => ['If-None-Match' => $etag]]);
        $this->assertSame(304, $notModified->status());
    }

    #[Test]
    public function a_missing_file_is_rejected(): void
    {
        $this->expectException(EorbahAPIError::class);

        new FileResponse($this->dir . '/absent.txt');
    }
}
