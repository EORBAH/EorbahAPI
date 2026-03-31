<?php

namespace EorBah545\Eorbahapi\Security\JWTAuth;

use Exception;

class JWT {
    private $secret;
    private $algorithm;
    
    public function __construct($secret, $algorithm = 'HS256') {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
    }
    
    public function sign($payload, $secretOrPrivateKey = null, $options = null) {
        if ($secretOrPrivateKey === null) {
            $secretOrPrivateKey = $this->secret;
        }
        
        $header = [
            'alg' => $this->algorithm,
            'typ' => 'JWT'
        ];
        
        $timestamp = time();
        
        $defaultClaims = [
            'iat' => $timestamp
        ];
        
        if ($options && isset($options['expiresIn'])) {
            $defaultClaims['exp'] = $timestamp + $this->parseTime($options['expiresIn']);
        }
        
        if ($options && isset($options['notBefore'])) {
            $defaultClaims['nbf'] = $timestamp + $this->parseTime($options['notBefore']);
        }
        
        $fullPayload = array_merge($defaultClaims, (array)$payload);
        
        $headerEncoded = $this->base64UrlEncode(json_encode($header));
        $payloadEncoded = $this->base64UrlEncode(json_encode($fullPayload));
        
        $signature = $this->createSignature($headerEncoded . '.' . $payloadEncoded, $secretOrPrivateKey);
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }
    
    public function verify($token, $secretOrPublicKey = null, $options = null) {
        if ($secretOrPublicKey === null) {
            $secretOrPublicKey = $this->secret;
        }
        
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JsonWebTokenError();
        }
        
        list($headerB64, $payloadB64, $signatureB64) = $parts;
        
        $header = json_decode($this->base64UrlDecode($headerB64), true);
        
        $this->verifySignature($headerB64 . '.' . $payloadB64, $signatureB64, $secretOrPublicKey, $header['alg']);
        
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            $error = new TokenExpiredError();
            $error->expiredAt = $payload['exp'];
            throw $error;
        }
        
        if (isset($payload['nbf']) && $payload['nbf'] > time()) {
            $error = new NotBeforeError();
            $error->date = $payload['nbf'];
            throw $error;
        }
        
        return $payload;
    }
    
    public function decode($token, $options = null) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JsonWebTokenError();
        }
        
        $payloadB64 = $parts[1];
        $payload = json_decode($this->base64UrlDecode($payloadB64), true);
        
        return $payload;
    }
    
    public function token_pair($userData) {
        $accessToken = $this->sign(
            ['user' => $userData],
            null,
            ['expiresIn' => '15m']
        );
        
        $refreshToken = $this->sign(
            ['sub' => $userData['id'] ?? null, 'type' => 'refresh'],
            null,
            ['expiresIn' => '7d']
        );
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken
        ];
    }
    
    public function is_expired($token) {
        try {
            $payload = $this->decode($token);
            
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            return true;
        }
    }
    
    private function createSignature($data, $secret) {
        $hash = hash_hmac('sha256', $data, $secret, true);
        return $this->base64UrlEncode($hash);
    }
    
    private function verifySignature($data, $signature, $secret, $alg) {
        $expected = $this->createSignature($data, $secret);
        
        if (!hash_equals($expected, $signature)) {
            throw new JsonWebTokenError();
        }
    }
    
    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    private function base64UrlDecode($data) {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $mod4 = strlen($data) % 4;
        if ($mod4) {
            $data .= substr('====', $mod4);
        }
        return base64_decode($data);
    }
    
    private function parseTime($time) {
        if (is_numeric($time)) {
            return (int)$time;
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
            return $number * $units[$unit];
        }
        
        return 0;
    }
}