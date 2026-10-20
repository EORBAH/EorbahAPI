<?php

namespace Eorbahapi\Security\HTTP;

use Eorbahapi\Exceptions\HTTPException;
use Eorbahapi\Request;

/**
 * Schéma de sécurité « HTTP Bearer ».
 *
 *     $app->get('/me', function (#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $token) {
 *         return ['token' => $token->getCredentials()];
 *     });
 *
 * Les en-têtes sont lus depuis la `Request` (injectée par le resolver ou
 * construite depuis les superglobales), ce qui rend le schéma testable.
 * En cas d'échec, une `HTTPException` 401 est levée (avec `WWW-Authenticate`).
 */
class HTTPBearer {
    private bool $autoError;
    private string $scheme;
    private ?string $bearerFormat;
    private string $description;

    public function __construct(
        bool $autoError = true,
        string $scheme = "Bearer",
        ?string $bearerFormat = null,
        string $description = ""
    ) {
        $this->autoError = $autoError;
        $this->scheme = $scheme;
        $this->bearerFormat = $bearerFormat;
        $this->description = $description;
    }

    public function __invoke(?Request $request = null): HTTPAuthorizationCredentials {
        $request ??= new Request();

        $authHeader = $request->getHeader('Authorization');

        if ($authHeader === null || $authHeader === '') {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Authorization header missing');
            }
            throw new HTTPException(401, 'Authorization header not found', $this->challengeHeaders());
        }

        if (!is_string($authHeader)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization header');
            }
            throw new HTTPException(401, 'Invalid authorization header', $this->challengeHeaders());
        }

        // Parse "Bearer token" ou "Basic credentials"
        $parts = explode(' ', $authHeader, 2);

        if (count($parts) !== 2) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization format');
            }
            throw new HTTPException(401, 'Invalid authorization format', $this->challengeHeaders());
        }

        $scheme = $parts[0];
        $credentials = $parts[1];

        // Optionnel: vérifier le schéma attendu
        if (strtolower($scheme) !== strtolower($this->scheme)) {
            if ($this->autoError) {
                $this->raiseUnauthorizedError('Invalid authorization scheme');
            }
            throw new HTTPException(401, 'Invalid authorization scheme', $this->challengeHeaders());
        }

        return new HTTPAuthorizationCredentials($scheme, $credentials);
    }

    public function getScheme(): string {
        return $this->scheme;
    }

    public function getBearerFormat(): ?string {
        return $this->bearerFormat;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function setAutoError(bool $autoError): void {
        $this->autoError = $autoError;
    }

    /**
     * Valeur de l'en-tête `WWW-Authenticate` associé au schéma.
     */
    public function challenge(): string {
        $wwwAuthenticate = $this->scheme;
        if ($this->bearerFormat) {
            $wwwAuthenticate .= ' format="' . $this->bearerFormat . '"';
        }
        if ($this->description) {
            $wwwAuthenticate .= ' description="' . $this->description . '"';
        }

        return $wwwAuthenticate;
    }

    private function challengeHeaders(): array {
        return ['WWW-Authenticate' => $this->challenge()];
    }

    private function raiseUnauthorizedError(string $detail): never {
        throw new HTTPException(401, $detail, $this->challengeHeaders());
    }
}
