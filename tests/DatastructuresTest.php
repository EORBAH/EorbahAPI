<?php

namespace Eorbahapi\Tests;

use Eorbahapi\Datastructures\Address;
use Eorbahapi\Datastructures\FormData;
use Eorbahapi\Datastructures\Headers;
use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Logger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DatastructuresTest extends TestCase
{
    private ?string $logFile = null;
    private string $originalErrorLog;

    protected function setUp(): void
    {
        $this->originalErrorLog = (string) ini_get('error_log');
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);

        if ($this->logFile !== null && is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    #[Test]
    public function headers_are_case_insensitive_and_countable(): void
    {
        $headers = new Headers(['Content-Type' => 'application/json', 'X-Trace' => 'abc']);

        $this->assertSame('application/json', $headers->get('content-type'));
        $this->assertSame('application/json', $headers['CONTENT-TYPE']);
        $this->assertTrue($headers->has('x-trace'));
        $this->assertFalse($headers->has('x-absent'));
        $this->assertCount(2, $headers);
        $this->assertSame('défaut', $headers->get('x-absent', 'défaut'));
    }

    #[Test]
    public function headers_expose_their_keys_and_values(): void
    {
        $headers = new Headers(['X-A' => '1', 'X-B' => '2']);

        $this->assertSame(['x-a', 'x-b'], array_map('strtolower', $headers->keys()));

        $values = [];
        foreach ($headers as $name => $value) {
            $values[strtolower($name)] = $value;
        }

        $this->assertSame(['x-a' => '1', 'x-b' => '2'], $values);
    }

    #[Test]
    public function headers_build_themselves_from_server_variables(): void
    {
        $backup = $_SERVER;
        $_SERVER['HTTP_X_CUSTOM'] = 'valeur';
        $_SERVER['CONTENT_TYPE'] = 'text/plain';

        try {
            $headers = Headers::fromGlobals();

            $this->assertSame('valeur', $headers->get('X-Custom'));
            $this->assertSame('text/plain', $headers->get('Content-Type'));
        } finally {
            $_SERVER = $backup;
        }
    }

    #[Test]
    public function address_serializes_host_and_port(): void
    {
        $address = new Address('127.0.0.1', 6379);

        $this->assertTrue($address->isComplete());
        $this->assertSame('127.0.0.1', $address->getHost());
        $this->assertSame(6379, $address->getPort());
        $this->assertSame('127.0.0.1:6379', (string) $address);
        $this->assertSame(
            ['host' => '127.0.0.1', 'port' => 6379, 'address' => '127.0.0.1:6379'],
            $address->jsonSerialize()
        );
    }

    #[Test]
    public function an_empty_address_is_unknown(): void
    {
        $address = new Address();

        $this->assertFalse($address->isComplete());
        $this->assertSame('unknown', (string) $address);
    }

    #[Test]
    public function form_data_reads_and_sanitizes_values(): void
    {
        $form = new FormData(['name' => '<b>Jean</b>', 'age' => '30']);

        $this->assertSame('<b>Jean</b>', $form->get('name'));
        $this->assertSame(['name', 'age'], array_keys($form->all()));
        $this->assertSame('&lt;b&gt;Jean&lt;/b&gt;', $form->xssClean('<b>Jean</b>'));

        $sanitized = $form->sanitizeInput([
            'name' => ['type' => 'string', 'max_length' => 3, 'xss_clean' => false],
            'age' => ['type' => 'int', 'xss_clean' => false],
        ]);

        $this->assertSame('<b>', $sanitized['name']);
        $this->assertSame(30, $sanitized['age']);
    }

    #[Test]
    public function upload_file_exposes_read_write_and_close(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'eorbah_ds');
        file_put_contents($path, 'abc');
        $handle = fopen($path, 'rb+');

        $file = new UploadFile('doc.txt', 'text/plain', $handle);

        $this->assertSame('doc.txt', $file->filename);
        $this->assertSame('text/plain', $file->content_type);
        $this->assertSame('abc', $file->read(3));

        $file->seek(0);
        $this->assertSame(3, $file->write('xyz'));
        $file->seek(0);
        $this->assertSame('xyz', $file->read(3));

        $file->close();
        $this->assertSame('xyz', file_get_contents($path));

        @unlink($path);
    }

    #[Test]
    public function logger_writes_json_lines_at_the_right_level(): void
    {
        $this->logFile = tempnam(sys_get_temp_dir(), 'eorbah_log');
        ini_set('error_log', $this->logFile);

        $logger = new Logger('warning');
        $logger->debug('ignoré');
        $logger->warning('attention', ['contexte' => true]);

        $raw = (string) file_get_contents($this->logFile);

        // error_log() préfixe la ligne ([date] ...) : on extrait l'objet JSON.
        preg_match('/\{.*\}/s', $raw, $matches);

        $this->assertNotEmpty($matches, 'aucune entrée JSON dans le journal');

        $entry = json_decode($matches[0], true);
        $this->assertIsArray($entry);
        $this->assertSame('WARNING', $entry['level']);
        $this->assertSame('attention', $entry['message']);
        $this->assertSame(['contexte' => true], $entry['context']);
    }
}
