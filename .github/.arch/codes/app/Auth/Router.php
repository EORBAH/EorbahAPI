<?php

namespace PhoenixAccount\Auth;

use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use EorBah545\Eorbahapi\Attributes\Depends;
use EorBah545\Eorbahapi\security\JWTAuth\JWT;
use EorBah545\Eorbahapi\security\RateLimiter;
use EorBah545\Eorbahapi\security\SecurityBase;
use EorBah545\Eorbahapi\datastructures\FormData;
use EorBah545\Eorbahapi\security\OAuth2\OAuth2AuthorizationCodeBearer;

class Router
{
    public function __invoke($router)
    {
        $router->get('/me', [$this, 'me']);
        $router->post('/health', [$this, 'health']);

        $router->post('/auth/sign-in', [$this, 'sign_in']);
        $router->post('/auth/sign-up', [$this, 'sign_up']);
        $router->post('/auth/sign-out', [$this, 'sign_out']);
        $router->post('/auth/refresh', [$this, 'refresh_token']);
        $router->post('/auth/password-reset', [$this, 'password_reset']);

        $router->get('/oauth2/google', [$this, 'google']);
        $router->get('/oauth2/google/confirm', [$this, 'google_confirm']);
    }

    public function health(Request $req, Response $res)
    {
        $res->JSONResponse([
            'status' => 'ok',
            'version' => '1.0.0',
            'timestamp' => '2026-07-08T12:00:00Z',
            'uptime' => 123456.78
        ]);
    }

    public function me(Request $req, Response $res, Service $service)
    {
        $access_token = $req->cookie('access_token') ?? 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyIjp7ImlkIjoidXNyXzAwMSIsInNpZCI6IjEifSwiZXhwIjoxNzg0NDcwNTQ4LCJpYXQiOjE3ODQ0Njg3NDh9.eDjTsU4xzfdeRo3We7MdgtH4G3e4MzM04Ea6IoOEYR8';

        if (!$access_token) {
            $res->status(401)->json([
                'error' => [
                    "code" => "UNAUTHORIZED",
                    "message" => "Access token is missing, invalid, or expired."
                ]
            ]);
            return;
        }

        $isExpired = $service->isJwtTokenExprired($access_token);

        if (!$isExpired) {
            $res->status(401)->json([
                'error' => [
                    "code" => "UNAUTHORIZED",
                    "message" => "Access token is missing, invalid, or expired."
                ]
            ]);
           return;
        }

        $res->status(200)->json([
            'data' => [
                'id' => 'usr_abc123',
                'userName' => 'John Doe',
                'userEmail' => 'john.doe@gmail.com',
                'email_verified' => true,
                'avatar_url' => '/users/1/profile-picture.webp',
                'created_at' => '2025-01-15T10:30:00Z',
                'updated_at' => '2026-06-20T08:12:00Z'
            ]
        ]);
    }

    /* Auth with email */
    /**
     * Summary of sign_in - Connection
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function sign_in(Request $req, Response $res)
    {
        $res->JSONResponse(['status' => 'ok']);
    }


    /**
     * Summary of signup - Inscription
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function sign_up(Request $req, Response $res)
    {
        $data = $res->body();

        $res->JSONResponse(['status' => 'ok']);
    }

    /**
     * Summary of signout - Inscription
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function sign_out(Request $req, Response $res)
    {
        $res->JSONResponse(['status' => 'ok']);
    }

    public function password_reset(Request $req, Response $res)
    {
        $res->JSONResponse(['status' => 'ok']);
    }

    /* Authentification oauth2 */
    public function google(
        Request $req,
        Response $res,
        #[Depends(class: OAuth2AuthorizationCodeBearer::class, args: [
            'https://accounts.google.com/o/oauth2/auth',
            'https://oauth2.googleapis.com/token',
            'https://oauth2.googleapis.com/token',
            ['email', 'profile'],
            true,
            'Google OAuth2'
        ])]
        OAuth2AuthorizationCodeBearer $oauth
    ) {
        $clientId = $_ENV['OAUTH2_CLIENT_ID'];
        $redirectUri = $_ENV['OAUTH2_REDIRECT_URI'];
        $state = bin2hex(random_bytes(16));

        $authUrl = $oauth->createAuthorizationUrl(
            clientId: $clientId,
            redirectUri: $redirectUri,
            scopes: [],
            state: $state,
            responseType: 'code'
        );

        $req->setSessionValue('oauth2_state', $state);
        $res->JSONResponse([
            'state' => $state
        ]);
        $res->redirect($authUrl);
    }

    public function google_confirm(
        Request $req,
        Response $res,
        #[Depends(class: OAuth2AuthorizationCodeBearer::class, args: [
            'https://accounts.google.com/o/oauth2/auth',
            'https://oauth2.googleapis.com/token',
            'https://oauth2.googleapis.com/token',
            ['email', 'profile'],
            true,
            'Google OAuth2'
        ])]
        OAuth2AuthorizationCodeBearer $oauth
    ) {
        $code = $req->query()['code'] ?? null;
        $state = $req->query()['state'] ?? null;

        # Vérification state CSRF
        if (!$state || $state !== ($req->session('oauth2_state') ?? null)) {
            $res->status(400)->json(['error' => 'Invalid state']);
            return;
        }

        if (!$code) {
            $res->status(400)->json(['error' => 'Missing authorization code']);
            return;
        }

        $clientId = 'VOTRE_CLIENT_ID';
        $clientSecret = 'VOTRE_CLIENT_SECRET';
        $redirectUri = 'https://votre-site.com/auth/callback';

        # Échange du code contre un token
        $tokenData = $oauth->exchangeCodeForToken($code, $clientId, $clientSecret, $redirectUri);

        # Stockage du token d'accès (dans session, base de données, etc.)
        $req->setSessionValue('access_token', $tokenData['access_token']);
        $req->setSessionValue('refresh_token', $tokenData['refresh_token']);

        $res->json(['message' => 'Authentification réussie', 'tokens' => $tokenData]);
    }

    /**
     * Summary of refresh_token
     * @param Request $req
     * @param Response $res
     * @return void
     */
    public function refresh_token(Request $req, Response $res)
    {
        $res->JSONResponse(['status' => 'ok']);
    }
}