<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Templating\TwigTemplates;

/**
 * Templates Twig rendus depuis une route.
 */
final class TemplatingExample extends DocsExample
{
    /**
     * Crée un petit répertoire de templates dans le dossier temporaire.
     */
    public static function templateDir(): string
    {
        $dir = sys_get_temp_dir() . '/eorbah_docs_templates';
        $cache = sys_get_temp_dir() . '/eorbah_docs_templates_cache';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (!is_dir($cache)) {
            mkdir($cache, 0777, true);
        }

        file_put_contents(
            $dir . '/index.html.twig',
            '<h1>{{ title }}</h1><ul>{% for item in items %}<li>{{ item }}</li>{% endfor %}</ul>'
        );

        return $dir;
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $templates = new TwigTemplates(
            self::templateDir(),
            sys_get_temp_dir() . '/eorbah_docs_templates_cache'
        );

        $app->register(TwigTemplates::class, $templates);

        // Le moteur de templates est injecté par son type.
        $app->get('/', function (TwigTemplates $twig) {
            return $twig->TemplateResponse('index.html.twig', [
                'title' => 'EorbahAPI',
                'items' => ['routes', 'params', 'tests'],
            ]);
        });

        // Rendu d'un template à la volée.
        $app->get('/inline', function (TwigTemplates $twig) {
            return $twig->createTemplate('Bonjour {{ name }}', ['name' => 'Oumar']);
        });

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/', contains: '<h1>EorbahAPI</h1><ul><li>routes</li><li>params</li><li>tests</li></ul>'),
            self::scenario('GET', '/inline', contains: 'Bonjour Oumar'),
        ];
    }
}
