<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\StaticFiles;

/**
 * Fichiers statiques montés sous un préfixe.
 */
final class StaticFilesExample extends DocsExample
{
    /**
     * Crée un petit répertoire public dans le dossier temporaire.
     */
    public static function publicDir(): string
    {
        $dir = sys_get_temp_dir() . '/eorbah_docs_static';

        if (!is_dir($dir . '/assets')) {
            mkdir($dir . '/assets', 0777, true);
        }

        file_put_contents($dir . '/index.html', '<h1>Documentation</h1>');
        file_put_contents($dir . '/assets/style.css', 'body{margin:0}');

        return $dir;
    }

    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Le préfixe /static est retiré avant de chercher le fichier.
        $app->mount('/static', new StaticFiles(self::publicDir()));

        $app->get('/api', fn () => ['api' => true]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/static/index.html', contains: '<h1>Documentation</h1>', headers: ['Content-Type' => 'text/html']),
            self::scenario('GET', '/static', contains: '<h1>Documentation</h1>'),
            self::scenario('GET', '/static/assets/style.css', contains: 'body{margin:0}', headers: ['Content-Type' => 'text/css']),
            self::scenario('GET', '/static/absent.css', status: 404, contains: 'File not found'),
            self::scenario('GET', '/api', json: ['api' => true]),
        ];
    }
}
