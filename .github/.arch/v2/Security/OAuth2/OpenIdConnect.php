<?php

namespace Eorbahapi\Security\OAuth2;

use Eorbahapi\DependencyInterface;
use Eorbahapi\Request;
use Eorbahapi\Response;
use Eorbahapi\Security\JWTAuth\JWT;
use Hvatum\OpenIDConnect\Client\Provider\OpenIDConnectProvider;

class OpenIdConnect implements DependencyInterface
{
    private array $config;
    private OpenIDConnectProvider $provider;
    private Request $request;
    private Response $response;
    private JWT $jwtHandler;

    /**
     * @param string $issuer L'URL de l'émetteur (ex: https://accounts.google.com)
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @param array $scopes Liste des scopes (au moins 'openid')
     * @param array $options Options supplémentaires (cache, proxy, etc.)
     */
    public function __construct(
        string $issuer,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        array $scopes = ['openid', 'profile', 'email'],
        array $options = []
    ) {
        // On utilise notre propre JWT pour la validation
        $this->jwtHandler = new JWT('', 'HS256');

        $this->config = [
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'issuer' => rtrim($issuer, '/'),
            'scope' => $scopes,
        ];

        // Configuration avancée (PKCE, cache...)
        if (isset($options['use_pkce'])) {
            $this->config['usePkce'] = $options['use_pkce'];
        }
        if (isset($options['cache_dir'])) {
            $this->config['cacheDir'] = $options['cache_dir'];
        }

        // Initialisation du fournisseur OIDC
        $this->provider = new OpenIDConnectProvider($this->config);
    }

    /**
     * Point d'entrée pour DependencyResolver.
     * Cette méthode est appelée automatiquement si l'attribut #[Depends] est utilisé.
     */
    public function resolve(Request $request, Response $response): mixed
    {
        $this->request = $request;
        $this->response = $response;
        return $this;
    }

    /**
     * Génère l'URL d'autorisation pour rediriger l'utilisateur.
     *
     * @param array $extraParams Paramètres additionnels (ex: 'prompt' => 'consent')
     * @return string
     */
    public function getAuthorizationUrl(array $extraParams = []): string
    {
        // Générer et stocker l'état (state) et le nonce en session
        $authUrl = $this->provider->getAuthorizationUrl($extraParams);
        
        // Stocker les valeurs dans la session via l'objet Request d'EorbahAPI
        $this->request->setSessionValue('oauth2_state', $this->provider->getState());
        $this->request->setSessionValue('oauth2_nonce', $this->provider->getNonce());
        if ($this->provider->getPkceCode()) {
            $this->request->setSessionValue('oauth2_pkce', $this->provider->getPkceCode());
        }
        
        return $authUrl;
    }

    /**
     * Échange le code d'autorisation contre un token.
     *
     * @param string $code Le code d'autorisation reçu dans la requête de callback
     * @return array Token (access_token, id_token, refresh_token...)
     * @throws \RuntimeException
     */
    public function authenticate(string $code): array
    {
        // Restaurer l'état (state) et le nonce depuis la session
        $state = $this->request->session('oauth2_state');
        $nonce = $this->request->session('oauth2_nonce');
        $pkce = $this->request->session('oauth2_pkce');
        
        // Valider l'état (state) pour prévenir les attaques CSRF
        $requestState = $this->request->query()['state'] ?? null;
        if (!$requestState || $requestState !== $state) {
            throw new \RuntimeException('Invalid state parameter');
        }
        
        // Restaurer le contexte du provider
        if ($nonce) {
            $this->provider->setNonce($nonce);
        }
        if ($pkce) {
            $this->provider->setPkceCode($pkce);
        }
        
        // Échanger le code contre un token
        $token = $this->provider->getAccessToken('authorization_code', [
            'code' => $code,
            'code_verifier' => $pkce,
        ]);
        
        // Valider l'ID Token
        $idToken = $token->getIdToken();
        if ($idToken) {
            $claims = $this->provider->validateIdToken($idToken, $nonce);
        }
        
        // Nettoyer la session
        $this->request->setSessionValue('oauth2_state', null);
        $this->request->setSessionValue('oauth2_nonce', null);
        $this->request->setSessionValue('oauth2_pkce', null);
        
        return [
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires_in' => $token->getExpires(),
            'id_token' => $idToken,
            'claims' => $claims ?? null,
        ];
    }

    /**
     * Rafraîchit un token d'accès.
     *
     * @param string $refreshToken
     * @return array
     */
    public function refreshToken(string $refreshToken): array
    {
        $token = $this->provider->getAccessToken('refresh_token', [
            'refresh_token' => $refreshToken,
        ]);
        
        return [
            'access_token' => $token->getToken(),
            'refresh_token' => $token->getRefreshToken(),
            'expires_in' => $token->getExpires(),
        ];
    }

    /**
     * Récupère les informations utilisateur (claims) via l'ID Token ou l'endpoint UserInfo.
     *
     * @param string $accessToken
     * @return array
     */
    public function getUserInfo(string $accessToken): array
    {
        $user = $this->provider->getResourceOwner(
            new \League\OAuth2\Client\Token\AccessToken(['access_token' => $accessToken])
        );
        
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'claims' => $user->toArray(),
        ];
    }
}