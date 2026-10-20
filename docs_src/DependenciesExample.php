<?php

namespace Eorbahapi\Examples;

use Eorbahapi\Dependency\DependencyInterface;
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Depends;
use Eorbahapi\Request;
use Eorbahapi\Response;

/**
 * Dépendance qui lit l'utilisateur courant dans les en-têtes.
 */
final class CurrentUser implements DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed
    {
        return $request->getHeader('X-User') ?? 'anonyme';
    }
}

/**
 * Service applicatif injecté par le conteneur.
 */
final class Greeter
{
    public function greet(string $name): string
    {
        return 'Bonjour ' . $name;
    }
}

/**
 * Fonction utilisée comme dépendance.
 */
function eorbah_api_greeting(): string
{
    return 'Bonjour depuis une fonction';
}

/**
 * Dépendances : `#[Depends]`, `DependencyInterface` et conteneur.
 */
final class DependenciesExample extends DocsExample
{
    public static function app(): EorbahAPI
    {
        $app = new EorbahAPI();

        // Une dépendance `DependencyInterface` reçoit la requête et la réponse.
        $app->get('/me', function (#[Depends(CurrentUser::class)] string $user) {
            return ['user' => $user];
        });

        // Une fonction peut servir de dépendance.
        $app->get('/greeting', function (#[Depends('\Eorbahapi\Examples\eorbah_api_greeting')] string $message) {
            return ['message' => $message];
        });

        // Service enregistré dans le conteneur puis injecté par son type.
        $app->register(Greeter::class, new Greeter());
        $app->get('/hello', function (Greeter $greeter, #[Depends(CurrentUser::class)] string $user) {
            return ['message' => $greeter->greet($user)];
        });

        // Instanciation automatique (constructeur sans paramètre requis).
        $app->get('/auto', fn (Greeter $greeter) => ['message' => $greeter->greet('monde')]);

        return $app;
    }

    public static function scenarios(): array
    {
        return [
            self::scenario('GET', '/me', json: ['user' => 'anonyme']),
            self::scenario('GET', '/me', ['headers' => ['X-User' => 'oumar']], json: ['user' => 'oumar']),
            self::scenario('GET', '/greeting', json: ['message' => 'Bonjour depuis une fonction']),
            self::scenario('GET', '/hello', json: ['message' => 'Bonjour anonyme']),
            self::scenario('GET', '/auto', json: ['message' => 'Bonjour monde']),
        ];
    }
}
