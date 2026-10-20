<?php

namespace Eorbahapi\Examples;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Routing\Attributes\Middleware;
use Eorbahapi\Routing\Attributes\Post;
use Eorbahapi\Routing\Attributes\Route;

/**
 * Middleware qui ajoute un identifiant de requête à la réponse.
 */
final class RequestIdMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        $response->headers['X-Request-Id'] = $request->getHeader('X-Request-Id') ?? 'genere';

        return $next();
    }
}

/**
 * Middleware qui refuse les requêtes sans en-tête d'autorisation.
 */
final class GuardMiddleware implements MiddlewareInterface
{
    public function __construct(private string $header = 'X-Token') {}

    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($request->getHeader($this->header) === null) {
            return new \Eorbahapi\Responses\JSONResponse(['error' => 'jeton manquant'], 403);
        }

        return $next();
    }
}

/**
 * Contrôleur illustrant les attributs de route et de middleware.
 */
final class MiddlewaresController
{
    #[Route('/login', methods: ['GET'])]
    #[Middleware(RequestIdMiddleware::class)]
    public function login()
    {
        return ['status' => 'ok'];
    }

    #[Post('/private')]
    #[Middleware(GuardMiddleware::class, args: ['header' => 'X-Token'])]
    public function privateArea()
    {
        return ['private' => true];
    }
}

/**
 * Middlewares : globaux, de route, et déclarés par attribut.
 */
final class MiddlewaresExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Middleware global : s'applique à toutes les routes.
        $app->add_middleware(RequestIdMiddleware::class);

        $app->get('/public', fn () => ['public' => true]);

        // Middleware de route via le paramètre de route.
        $app->get('/guarded', fn () => ['guarded' => true], [GuardMiddleware::class]);

        // Contrôleur avec #[Middleware].
        $app->include_controller(new MiddlewaresController());

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/public', json: ['public' => true], headers: ['X-Request-Id' => 'genere']),
            self::scenario('GET', '/guarded', status: 403, contains: 'jeton manquant'),
            self::scenario('GET', '/guarded', ['headers' => ['X-Token' => 'abc']], json: ['guarded' => true]),
            self::scenario('GET', '/login', json: ['status' => 'ok']),
            self::scenario('POST', '/private', ['headers' => ['X-Token' => 'abc']], json: ['private' => true]),
            self::scenario('POST', '/private', status: 403, contains: 'jeton manquant'),
        ];
    }
}
