# Recettes — patterns prêts à l'emploi

## CRUD minimal

```php
use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\{Body, Path};
use Eorbahapi\Responses\JSONResponse;

$app = new EorbahAPI();

$app->get('/items', fn () => ['items' => []]);
$app->post('/items', fn (#[Body] Item $item) => new JSONResponse(['item' => $item->model_dump()], 201));
$app->get('/items/{id}', fn (#[Path(ge: 1)] int $id) => ['id' => $id]);
$app->put('/items/{id}', fn (#[Path(ge: 1)] int $id, #[Body] Item $item) => ['updated' => $id]);
$app->delete('/items/{id}', fn (#[Path(ge: 1)] int $id) => new JSONResponse(null, 204));
```

## Contrôleur avec attributs

```php
use Eorbahapi\Routing\Attributes\{Get, Post, Middleware};
use Eorbahapi\Middlewares\RateLimitingMiddleware;

final class AuthController
{
    #[Post('/login')]
    #[Middleware(RateLimitingMiddleware::class, args: ['maxRequests' => 5])]
    public function login() { return ['status' => 'ok']; }

    #[Get('/me')]
    public function me() { return ['me' => true]; }
}

$app->include_controller(new AuthController(), prefix: '/auth');
```

## Authentification par jeton

```php
use Eorbahapi\Params\Security;
use Eorbahapi\Security\HTTP\{HTTPBearer, HTTPAuthorizationCredentials};
use Eorbahapi\Security\JWTAuth\JWT;

$app->get('/me', function (
    #[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials,
    JWT $jwt
) {
    $payload = $jwt->verify($credentials->getCredentials()); // TokenExpiredError si expiré
    return ['user' => $payload['sub']];
});
```

## Dépendance avec accès à la requête

```php
use Eorbahapi\Dependency\DependencyInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;

final class CurrentUser implements DependencyInterface
{
    public function resolve(Request $request, Response $response): mixed
    {
        return $request->getHeader('X-User') ?? 'anonyme';
    }
}

$app->get('/me', fn (#[Depends(CurrentUser::class)] string $user) => ['user' => $user]);
```

## Middleware

```php
use Eorbahapi\Middlewares\MiddlewareInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Responses\JSONResponse;

final class AuthMiddleware implements MiddlewareInterface
{
    public function __invoke(Request $request, Response $response, callable $next): mixed
    {
        if ($request->getHeader('Authorization') === null) {
            return new JSONResponse(['error' => 'non authentifié'], 401);
        }

        return $next();
    }
}

$app->add_middleware(AuthMiddleware::class);
$app->get('/protégé', fn () => 'ok', [AuthMiddleware::class]);
```

## Upload de fichier

```php
use Eorbahapi\Datastructures\UploadFile;
use Eorbahapi\Params\File;

$app->post('/upload', function (#[File] UploadFile $file) {
    $file->write('contenu');
    $file->seek(0);

    return ['name' => $file->filename, 'type' => $file->content_type, 'preview' => $file->read(64)];
});
```

## Server-Sent Events

```php
use Eorbahapi\Responses\EventStreamResponse;

$app->get('/events', fn () => new EventStreamResponse((static function (): \Generator {
    for ($i = 0; $i < 5; $i++) {
        yield "data: tick $i\n\n";
    }
})()));
```

## Fichiers statiques + API sur la même application

```php
use Eorbahapi\StaticFiles;

$app->mount('/static', new StaticFiles(__DIR__ . '/public'));
$app->get('/api/ping', fn () => ['pong' => true]);
```

## Runner personnalisé (file d'attente, CLI)

```php
use Eorbahapi\Request;
use Eorbahapi\Response;

$response = $app->handle(new Request(
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/items'],
    body: ['name' => 'Clavier'],
    headers: ['Content-Type' => 'application/json'],
), new Response());
```

## Sortie JSON-RPC

```php
use Eorbahapi\RPC\JsonRPC;

$rpc = new JsonRPC();
$rpc->add_method('math.sum', fn (int $a, int $b) => $a + $b);
$app->mount('/rpc', $rpc);
```

## Personnaliser les erreurs

```php
use Eorbahapi\Responses\JSONResponse;

$app->setExceptionHandler(\DomainException::class, fn (\Throwable $e) => new JSONResponse(
    ['error' => $e->getMessage()],
    400
));
```
