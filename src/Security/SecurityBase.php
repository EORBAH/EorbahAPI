<?php
 
namespace Eorbahapi\Security;

class SecurityBase {
    /**
     * Summary of setNonceClient
     * @return string
     */
    public function setNonceClient()
    {
        $nonce = bin2hex(random_bytes(32));

        header("Content-Security-Policy: script-src 'self' 'nonce-$nonce'; font-src 'self'; object-src 'none';");
        return $nonce;
    }

    /**
     * Summary of generateAppToken
     * @param string $data
     * @param string $secretKey
     * @return string
     */
    public function generateJWTAppToken(string $data, string $secretKey): string
    {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'data' => $data,
            'iat' => time(),
            'exp' => time() + (86400 * 30)
        ]);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secretKey, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Summary of validateAppToken
     * @param string $token
     * @param string $secretKey
     */
    public function validateJWTAppToken(string $token, string $secretKey): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3)
            return null;

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Signature));
        $expectedSignature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $secretKey, true);

        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64Payload)), true);

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
    
    /**
     * Summary of generateBasicToken
     * @param mixed $data
     * @param mixed $expires_in
     * @param mixed $secret
     * @return string
     */
    public function generateBasicToken($data, $expires_in = 300, $secret)
    {
        $payload = [
            'data' => $data,
            'exp' => time() + $expires_in
        ];
        $payload_encoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $payload_encoded, $secret);
        return $payload_encoded . '.' . $signature;
    }
    
    /**
     * Summary of verifyBasicToken
     * @param mixed $token
     * @param mixed $secret
     */
    public function verifyBasicToken($token, $secret)
    {
        [$payload_encoded, $signature] = explode('.', $token);
        $expected_signature = hash_hmac('sha256', $payload_encoded, $secret);
        if (!hash_equals($expected_signature, $signature))
            return false;
        $payload = json_decode(base64_decode($payload_encoded), true);
        if (time() > $payload['exp'])
            return false;
        return $payload['data'];
    }
}