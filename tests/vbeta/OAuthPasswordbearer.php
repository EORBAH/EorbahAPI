<?php

namespace EorBah545\Eorbahapi\security\OAuth2;

use EorBah545\Eorbahapi\DependencyInterface;
use EorBah545\Eorbahapi\Request;
use EorBah545\Eorbahapi\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class OAuth2PasswordBearer extends OAuth2 implements DependencyInterface
{
    private ?Request $request = null;
    private ?Response $response = null;

    private UserProviderInterface $userProvider;
    private string $jwtSecret;
    private string $jwtAlgo;
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
        $this->jwtSecret = $jwtSecret;
        $this->jwtAlgo = $jwtAlgo;
        $this->tokenExpiration = $tokenExpiration;
    }

    /**
     * Appelé automatiquement par DependencyResolver si l'attribut #[Depends] est utilisé.
     * Permet d'injecter Request/Response si nécessaire (par exemple pour lire les identifiants depuis le body).
     */
    public function resolve(Request $request, Response $response): mixed
    {
        $this->request = $request;
        $this->response = $response;
        return $this;
    }

    /**
     * Valide les identifiants username/password et retourne un access token JWT.
     * Utilise le UserProvider pour vérifier les identifiants.
     *
     * @param string $username
     * @param string $password
     * @return array
     * @throws \Exception
     */
    public function validatePasswordGrant(string $username, string $password): array
    {
        // Vérification des identifiants via le provider
        $user = $this->userProvider->findUserByCredentials($username, $password);
        if (!$user) {
            // En cas d'erreur, on peut lever une exception ou retourner une structure d'erreur OAuth2
            throw new \Exception('Invalid credentials', 401);
        }

        // Génération du JWT
        $payload = [
            'sub' => $user->getIdentifier(),  // ex: id, email
            'username' => $user->getUsername(),
            'scopes' => $this->scopes,
            'iat' => time(),
            'exp' => time() + $this->tokenExpiration
        ];

        $accessToken = JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);

        return [
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->tokenExpiration
        ];
    }

    /**
     * Optionnel : méthode pour décoder et vérifier un token (pour les endpoints protégés)
     */
    public function verifyToken(string $token): ?object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgo));
        } catch (\Exception $e) {
            return null;
        }
    }
}