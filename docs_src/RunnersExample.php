<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

/**
 * Runners personnalisés : `handle()` construit la réponse sans l'envoyer, ce
 * qui permet d'alimenter l'application depuis n'importe quelle source (CLI,
 * file d'attente, test, autre framework...).
 */
final class RunnersExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        $app->get('/tasks', fn () => ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]);
        $app->post('/tasks', fn (Request $request) => new JSONResponse(
            ['created' => $request->body('title')],
            201
        ));

        return $app;
    }

    /**
     * Un « runner » qui traite des requêtes sans passer par le serveur web.
     *
     * @return list<Response>
     */
    public static function runner(): array
    {
        $app = self::app();

        $requests = [
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tasks']),
            new Request(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/tasks'],
                body: ['title' => 'Relire la doc'],
                headers: ['Content-Type' => 'application/json'],
            ),
        ];

        $responses = [];
        foreach ($requests as $request) {
            $responses[] = $app->handle($request, new Response());
        }

        return $responses;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/tasks', json: ['tasks' => [['id' => 1, 'title' => 'Écrire la doc']]]),
            self::scenario('POST', '/tasks', ['json' => ['title' => 'Relire la doc']], status: 201, json: ['created' => 'Relire la doc']),
        ];
    }
}
