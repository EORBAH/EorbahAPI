<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Exceptions\EorbahAPIError;
use Eorbahapi\Request;
use Eorbahapi\StaticFiles;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Compatibilité de StaticFiles avec le pipeline handle()/TestClient.
 */
class StaticFilesTest extends TestCase
{
    private string $dir;
    private string $app;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/eorbah_static_' . uniqid();
        mkdir($this->dir . '/assets', 0777, true);

        file_put_contents($this->dir . '/index.html', '<h1>Accueil</h1>');
        file_put_contents($this->dir . '/assets/style.css', 'body{color:red}');
        file_put_contents($this->dir . '/assets/app.js', 'console.log("ok")');
        file_put_contents($this->dir . '/secret.php', '<?php echo "pwned";');
        file_put_contents($this->dir . '/assets/body.txt', str_repeat('lorem ipsum ', 200));

        $this->app = $this->dir;
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->dir);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }

        @rmdir($dir);
    }

    private function mountedApp(array $options = []): EorbahAPI
    {
        $app = new EorbahAPI();
        $app->mount('/static', new StaticFiles($this->app, $options));

        return $app;
    }

    #[Test]
    public function it_serves_an_html_file(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static/index.html');

        $this->assertSame(200, $response->status());
        $this->assertSame('<h1>Accueil</h1>', $response->text());
        $this->assertSame('text/html', $response->header('Content-Type'));
        $this->assertNotNull($response->header('ETag'));
        $this->assertNotNull($response->header('Last-Modified'));
        $this->assertSame('no-cache, no-store, must-revalidate', $response->header('Cache-Control'));
    }

    #[Test]
    public function it_serves_nested_files_with_the_right_mime_type(): void
    {
        $client = new TestClient($this->mountedApp());

        $css = $client->get('/static/assets/style.css');
        $this->assertSame(200, $css->status());
        $this->assertSame('text/css', $css->header('Content-Type'));
        $this->assertSame('body{color:red}', $css->text());

        $js = $client->get('/static/assets/app.js');
        $this->assertSame('application/javascript', $js->header('Content-Type'));
    }

    #[Test]
    public function it_serves_the_index_for_a_directory(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static');

        $this->assertSame(200, $response->status());
        $this->assertSame('<h1>Accueil</h1>', $response->text());
    }

    #[Test]
    public function an_unknown_file_returns_404(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static/absent.css');

        $this->assertSame(404, $response->status());
        $this->assertSame('File not found', $response->text());
    }

    #[Test]
    public function files_outside_the_allowed_extensions_are_refused(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static/secret.php');

        $this->assertSame(404, $response->status());
    }

    #[Test]
    public function it_does_not_escape_the_served_directory(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static/../../../etc/passwd');

        $this->assertSame(404, $response->status());
    }

    #[Test]
    public function a_matching_etag_returns_304(): void
    {
        $client = new TestClient($this->mountedApp());

        $first = $client->get('/static/index.html');
        $etag = $first->header('ETag');
        $this->assertNotNull($etag);

        $second = $client->get('/static/index.html', ['headers' => ['If-None-Match' => $etag]]);

        $this->assertSame(304, $second->status());
        $this->assertSame('', $second->text());
    }

    #[Test]
    public function an_old_if_modified_since_returns_304(): void
    {
        $client = new TestClient($this->mountedApp());

        $response = $client->get('/static/index.html', [
            'headers' => ['If-Modified-Since' => gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT'],
        ]);

        $this->assertSame(304, $response->status());
    }

    #[Test]
    public function it_compresses_text_files_when_gzip_is_accepted(): void
    {
        $response = (new TestClient($this->mountedApp()))->get('/static/assets/body.txt', [
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);

        $this->assertSame(200, $response->status());
        $this->assertSame('gzip', $response->header('Content-Encoding'));
        $this->assertSame('Accept-Encoding', $response->header('Vary'));
        $this->assertSame(str_repeat('lorem ipsum ', 200), gzdecode($response->text()));
    }

    #[Test]
    public function compression_can_be_disabled(): void
    {
        $response = (new TestClient($this->mountedApp(['compression' => false])))->get('/static/assets/body.txt', [
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);

        $this->assertNull($response->header('Content-Encoding'));
        $this->assertSame(str_repeat('lorem ipsum ', 200), $response->text());
    }

    #[Test]
    public function an_invalid_directory_is_rejected(): void
    {
        $this->expectException(EorbahAPIError::class);

        new StaticFiles($this->dir . '/does-not-exist');
    }

    #[Test]
    public function static_files_work_without_mount_when_called_directly(): void
    {
        $files = new StaticFiles($this->dir);
        $response = $files->handle(new Request(server: [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/assets/style.css',
        ]));

        $this->assertSame(200, $response->status);
        $this->assertSame('body{color:red}', $response->body);
    }
}
