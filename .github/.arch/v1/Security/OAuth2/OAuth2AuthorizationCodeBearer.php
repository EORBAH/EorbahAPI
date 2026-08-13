<?php

namespace EorBah545\Eorbahapi\Security\OAuth2;

class OAuth2AuthorizationCodeBearer extends OAuth2
{
    private string $authorizationUrl;
    private string $refreshUrl;

    public function __construct(
        string $authorizationUrl,
        string $tokenUrl,
        string $refreshUrl = "",
        array $scopes = [],
        bool $autoError = true,
        string $schemeName = "OAuth2",
        ?string $description = null
    ) {
        parent::__construct($tokenUrl, $scopes, $autoError, $schemeName, $description);
        $this->authorizationUrl = $authorizationUrl;
        $this->refreshUrl = $refreshUrl;
    }

    public function getAuthorizationUrl(): string
    {
        return $this->authorizationUrl;
    }

    public function getRefreshUrl(): string
    {
        return $this->refreshUrl;
    }

    public function createAuthorizationUrl(
        string $clientId,
        string $redirectUri,
        array $scopes = [],
        string $state,
        string $responseType = "code"
    ): string {
        $scopeList = empty($scopes) ?
            implode(' ', $this->getScopes()) :  // Utiliser le getter
            implode(' ', $scopes);

        $params = [
            'response_type' => $responseType,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scopeList
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $this->authorizationUrl . '?' . http_build_query($params);
    }

    public function exchangeCodeForToken(
        string $code,
        string $clientId,
        string $clientSecret,
        string $redirectUri
    ): array {
        return [
            'access_token' => $this->generateAccessToken($clientId),
            'token_type' => 'bearer',
            'expires_in' => 3600,
            'refresh_token' => $this->generateRefreshToken($clientId),
            'scope' => implode(' ', $this->getScopes())  // Utiliser le getter
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        return [
            'access_token' => $this->generateAccessToken('refreshed'),
            'token_type' => 'bearer',
            'expires_in' => 3600
        ];
    }

    private function generateAccessToken(string $clientId): string
    {
        return 'access_' . bin2hex(random_bytes(16)) . '_' . $clientId;
    }

    private function generateRefreshToken(string $clientId): string
    {
        return 'refresh_' . bin2hex(random_bytes(16)) . '_' . $clientId;
    }

    public function validateAuthorizationCode(array $queryParams): bool
    {
        // Valider les paramètres de retour OAuth2
        return isset($queryParams['code']) &&
            (!isset($queryParams['error']) || empty($queryParams['error']));
    }
}