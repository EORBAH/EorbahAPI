<?php

namespace Eorbahapi\Security\OAuth2;

use Eorbahapi\DependencyInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\JsonWebTokenError;
use Eorbahapi\Security\JWTAuth\TokenExpiredError;

class OAuth2PasswordBearer extends OAuth2 implements DependencyInterface
{
    private ?Request $request = null;
    private ?Response $response = null;

    private UserProviderInterface $userProvider;
    private JWT $jwtHandler;
    private int $tokenExpiration;

    public function __construct(
        UserProviderInterface $userProvider,
        string $jwtSecret,
        string $jwtAlgo = 'HS256',
        int $tokenExpiration = 3600,
        string $tokenUrl = "token",
        array $scopes = [],
        bool $autoError = true,
        string $schemeName = "OAuth2",
        ?string $description = null
    ) {
        parent::__construct($tokenUrl, $scopes, $autoError, $schemeName, $description);
        $this->userProvider = $userProvider;
        $this->jwtHandler = new JWT($jwtSecret, $jwtAlgo);
        $this->tokenExpiration = $tokenExpiration;
    }

    public function resolve(Request $request, Response $response): mixed
    {
        $this->request = $request;
        $this->response = $response;
        return $this;
    }

    /**
     * Valide les identifiants username/password et retourne un access token JWT.
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    public function validatePasswordGrant(string $username, string $password): array
    {
        $user = $this->userProvider->findUserByCredentials($username, $password);
        if (!$user) {
            throw new \Exception('Invalid credentials', 401);
        }

        $payload = [
            'sub' => $user->getIdentifier(),
            'username' => $user->getUsername(),
            'scopes' => $this->getScopes(),
            'iat' => time(),
        ];

        // Notre JWT::sign accepte un tableau d'options avec 'expiresIn' (en secondes ou chaîne comme '1h')
        $accessToken = $this->jwtHandler->sign($payload, null, ['expiresIn' => $this->tokenExpiration]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpiration
        ];
    }

    /**
     * Vérifie et décode un token JWT.
     *
     * @param string $token
     * @return object|null
     */
    public function verifyToken(string $token): ?object
    {
        try {
            $payload = $this->jwtHandler->verify($token);
            return (object) $payload;
        } catch (JsonWebTokenError | TokenExpiredError $e) {
            return null;
        }
    }
}