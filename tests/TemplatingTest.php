<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Testing\TestClient;
use Eorbahapi\Templating\TwigTemplates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TemplatingTest extends TestCase
{
    private string $dir;
    private string $cache;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/eorbah_tpl_' . uniqid();
        $this->cache = sys_get_temp_dir() . '/eorbah_tpl_cache_' . uniqid();
        mkdir($this->dir, 0777, true);
        mkdir($this->cache, 0777, true);

        file_put_contents(
            $this->dir . '/hello.html.twig',
            '<h1>Bonjour {{ name }}</h1>{% for i in items %}<li>{{ i }}</li>{% endfor %}'
        );
        file_put_contents(
            $this->dir . '/base.html.twig',
            '{% block content %}base{% endblock %}'
        );
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->cache] as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                is_dir($file) ? $this->removeDir($file) : @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $file) {
            is_dir($file) ? $this->removeDir($file) : @unlink($file);
        }
        @rmdir($dir);
    }

    #[Test]
    public function twig_renders_a_template_file(): void
    {
        $twig = new TwigTemplates($this->dir, $this->cache);

        $html = $twig->TemplateResponse('hello.html.twig', ['name' => 'Oumar', 'items' => ['a', 'b']]);

        $this->assertSame('<h1>Bonjour Oumar</h1><li>a</li><li>b</li>', $html);
    }

    #[Test]
    public function twig_renders_an_inline_template(): void
    {
        $twig = new TwigTemplates($this->dir, $this->cache);

        $this->assertSame('Total : 3', $twig->createTemplate('Total : {{ total }}', ['total' => 3]));
    }

    #[Test]
    public function a_route_can_return_a_rendered_template(): void
    {
        $templates = new TwigTemplates($this->dir, $this->cache);

        $app = new EorbahAPI();
        $app->register(TwigTemplates::class, $templates);
        $app->get('/', fn (TwigTemplates $twig) => $twig->TemplateResponse('hello.html.twig', [
            'name' => 'API',
            'items' => [],
        ]));

        $response = (new TestClient($app))->get('/');

        $this->assertSame('<h1>Bonjour API</h1>', $response->text());
    }

}
