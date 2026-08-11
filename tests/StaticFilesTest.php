<?php

namespace Eorbahapi\Tests;

use PHPUnit\Framework\TestCase;
use Eorbahapi\StaticFiles;
use Eorbahapi\Request;
use Eorbahapi\Response;

class StaticFilesTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/eorbahapi_static_' . uniqid();
        mkdir($this->tempDir, 0777, true);
        file_put_contents($this->tempDir . '/index.html', '<h1>OK</h1>');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDir . '/index.html')) {
            unlink($this->tempDir . '/index.html');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testServeReturnsTrueForExistingFile(): void
    {
        $static = new StaticFiles($this->tempDir, ['index' => 'index.html']);

        ob_start();
        $result = $static->serve('/');
        $output = ob_get_clean();

        $this->assertTrue($result);
        $this->assertStringContainsString('<h1>OK</h1>', $output);
    }

    public function testServeReturnsFalseForMissingFile(): void
    {
        $static = new StaticFiles($this->tempDir, ['index' => 'missing.html']);
        $result = $static->serve('/');

        $this->assertFalse($result);
    }
}
