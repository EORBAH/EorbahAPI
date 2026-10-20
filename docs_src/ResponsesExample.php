<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Responses\HTMLResponse;
use Eorbahapi\Responses\JSONResponse;
use Eorbahapi\Responses\RedirectResponse;

/**
 * Réponses : statut, en-têtes, contenu, redirections.
 */
final class ResponsesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Un tableau est encodé en JSON automatiquement.
        $app->get('/auto', fn () => ['ok' => true]);

        // Contrôle explicite du statut et des en-têtes.
        $app->post('/created', fn () => new JSONResponse(['created' => true], 201, ['X-Trace' => 'abc']));

        // Réponse HTML.
        $app->get('/html', fn () => new HTMLResponse('<h1>Bonjour</h1>'));

        // Redirection typée.
        $app->get('/go', fn () => new RedirectResponse('/auto', 307));

        // Redirection raccourcie : "redirect:code:url".
        $app->get('/short', fn () => 'redirect:301:/auto');

        // Texte brut.
        $app->get('/text', fn () => 'bonjour');

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/auto', json: ['ok' => true]),
            self::scenario(
                'POST',
                '/created',
                status: 201,
                json: ['created' => true],
                headers: ['X-Trace' => 'abc']
            ),
            self::scenario('GET', '/html', contains: '<h1>Bonjour</h1>', headers: ['Content-Type' => 'text/html; charset=UTF-8']),
            self::scenario('GET', '/go', status: 307, headers: ['Location' => '/auto']),
            self::scenario('GET', '/short', status: 301, headers: ['Location' => '/auto']),
            self::scenario('GET', '/text', contains: 'bonjour'),
        ];
    }
}
