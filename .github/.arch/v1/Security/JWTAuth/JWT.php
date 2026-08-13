<?php

namespace EorBah545\Eorbahapi\Security\JWTAuth;

use Exception;
use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;

class JWT
{
    private string $secret;
    private string $algorithm;
    
    /**
     * @param string $secret   Clé secrète (pour HMAC) ou clé privée/publique (pour RSA/ECDSA)
     * @param string $algorithm Algorithme supporté par firebase/php-jwt (ex: 'HS256', 'RS256', 'ES384')
     */
    public function __construct(string $secret, string $algorithm = 'HS256')
    {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
    }
    
    /**
     * Signe un payload et retourne un JWT.
     *
     * @param array       $payload
     * @param string|null $secretOrPrivateKey Si null, utilise le secret du constructeur
     * @param array       $options            ['expiresIn' => int|string, 'notBefore' => ...]
     * @return string
     */
    public function sign(array $payload, ?string $secretOrPrivateKey = null, array $options = []): string
    {
        $key = $secretOrPrivateKey ?? $this->secret;
        $payloadToSign = $payload;
        
        $now = time();
        if (isset($options['expiresIn'])) {
            $payloadToSign['exp'] = $now + $this->parseTime($options['expiresIn']);
        }
        if (isset($options['notBefore'])) {
            $payloadToSign['nbf'] = $now + $this->parseTime($options['notBefore']);
        }
        if (!isset($payloadToSign['iat'])) {
            $payloadToSign['iat'] = $now;
        }
        
        return FirebaseJWT::encode($payloadToSign, $key, $this->algorithm);
    }
    
    /**
     * Vérifie un token et retourne le payload (tableau).
     *
     * @param string      $token
     * @param string|null $secretOrPublicKey Si null, utilise le secret du constructeur
     * @return array
     * @throws JsonWebTokenError
     * @throws TokenExpiredError
     * @throws NotBeforeError
     */
    public function verify(string $token, ?string $secretOrPublicKey = null): array
    {
        $key = $secretOrPublicKey ?? $this->secret;
        
        try {
            $decoded = FirebaseJWT::decode($token, new Key($key, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException $e) {
            $error = new TokenExpiredError();
            $error->expiredAt = null; // On pourrait extraire le claim 'exp' si besoin
            throw $error;
        } catch (BeforeValidException $e) {
            throw new NotBeforeError($e->getMessage());
        } catch (SignatureInvalidException $e) {
            throw new JsonWebTokenError($e->getMessage());
        } catch (Exception $e) {
            throw new JsonWebTokenError($e->getMessage());
        }
    }
    
    /**
     * Décode un token sans vérifier la signature (attention : usage interne uniquement).
     */
    public function decode(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JsonWebTokenError('Token malformé');
        }
        $payloadB64 = $parts[1];
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        if (!is_array($payload)) {
            throw new JsonWebTokenError('Payload invalide');
        }
        return $payload;
    }
    
    /**
     * Vérifie si un token est expiré (ne vérifie pas la signature).
     */
    public function isExpired(string $token): bool
    {
        try {
            $payload = $this->decode($token);
            return isset($payload['exp']) && $payload['exp'] < time();
        } catch (Exception $e) {
            return true;
        }
    }
    
    /**
     * Génère une paire access_token / refresh_token.
     *
     * @param array $userData
     * @param int   $accessExpiresIn  Durée de vie de l'access token (secondes)
     * @param int   $refreshExpiresIn Durée de vie du refresh token (secondes)
     * @return array
     */
    public function tokenPair(array $userData, int $accessExpiresIn = 900, int $refreshExpiresIn = 604800): array
    {
        $accessToken = $this->sign(
            ['user' => $userData],
            null,
            ['expiresIn' => $accessExpiresIn]
        );
        
        $refreshToken = $this->sign(
            ['sub' => $userData['id'] ?? null, 'sid' => $userData['sid'] ?? null,  'type' => 'refresh'],
            null,
            ['expiresIn' => $refreshExpiresIn]
        );
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $accessExpiresIn,
            'token_type'  => 'bearer'
        ];
    }
    
    /**
     * Convertit une durée relative en secondes (ex: '15m', '1h', '7d')
     */
    private function parseTime($time): int
    {
        if (is_numeric($time)) {
            return (int) $time;
        }
        $units = [
            's' => 1,
            'm' => 60,
            'h' => 3600,
            'd' => 86400,
            'w' => 604800
        ];
        $unit = substr($time, -1);
        $number = substr($time, 0, -1);
        if (isset($units[$unit]) && is_numeric($number)) {
            return (int) $number * $units[$unit];
        }
        return 0;
    }
    
    private function base64UrlDecode(string $data): string
    {
        $data = strtr($data, '-_', '+/');
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= str_repeat('=', 4 - $mod4);
        }
        return base64_decode($data);
    }
}