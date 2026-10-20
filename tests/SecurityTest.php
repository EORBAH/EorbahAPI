<?php

namespace Eorbahapi\Tests;

use Eorbahapi\EorbahAPI;
use Eorbahapi\Params\Security;
use Eorbahapi\Security\APIKey\APIKeyCookie;
use Eorbahapi\Security\APIKey\APIKeyHeader;
use Eorbahapi\Security\APIKey\APIKeyQuery;
use Eorbahapi\Security\HTTP\HTTPAuthorizationCredentials;
use Eorbahapi\Security\HTTP\HTTPBasic;
use Eorbahapi\Security\HTTP\HTTPBearer;
use Eorbahapi\Security\JWTAuth\JWT;
use Eorbahapi\Security\JWTAuth\TokenExpiredError;
use Eorbahapi\Security\Nonce;
use Eorbahapi\Security\OAuth2\OAuth2;
use Eorbahapi\Testing\TestClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    #[Test]
    public function bearer_scheme_exposes_the_token(): void
    {
        $app = new EorbahAPI();
        $app->get('/me', fn (#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials) => [
            'scheme' => $credentials->getScheme(),
            'token' => $credentials->getCredentials(),
        ]);

        $response = (new TestClient($app))->get('/me', [
            'headers' => ['Authorization' => 'Bearer abc.def.ghi'],
        ]);

        $this->assertSame(['scheme' => 'Bearer', 'token' => 'abc.def.ghi'], $response->json());
    }

    #[Test]
    public function a_missing_bearer_token_is_a_401_with_a_challenge(): void
    {
        $app = new EorbahAPI();
        $app->get('/me', fn (#[Security(HTTPBearer::class)] HTTPAuthorizationCredentials $credentials) => 'ok');

        $response = (new TestClient($app))->get('/me');

        $this->assertSame(401, $response->status());
        $this->assertSame('Bearer', $response->header('WWW-Authenticate'));
    }

    #[Test]
    public function basic_scheme_decodes_the_credentials(): void
    {
        $app = new EorbahAPI();
        $app->get('/basic', fn (#[Security(HTTPBasic::class)] HTTPAuthorizationCredentials $credentials) => [
            'decoded' => $credentials->getCredentials(),
        ]);

        $encoded = base64_encode('oumar:secret');
        $response = (new TestClient($app))->get('/basic', [
            'headers' => ['Authorization' => 'Basic ' . $encoded],
        ]);

        $this->assertSame(['decoded' => 'oumar:secret'], $response->json());
    }

    #[Test]
    public function api_key_header_scheme_reads_a_custom_header(): void
    {
        $app = new EorbahAPI();
        $app->get('/key', fn (#[Security(APIKeyHeader::class)] string $key) => $key);

        // Le schéma est instancié par défaut (en-tête X-API-Key).
        $response = (new TestClient($app))->get('/key', ['headers' => ['X-API-Key' => 'secret']]);

        $this->assertSame('secret', $response->text());
    }

    #[Test]
    public function api_key_query_scheme_reads_the_query_string(): void
    {
        $app = new EorbahAPI();
        $app->get('/key', fn (#[Security(APIKeyQuery::class)] string $key) => $key);

        $this->assertSame('secret', (new TestClient($app))->get('/key?api_key=secret')->text());
    }

    #[Test]
    public function api_key_cookie_scheme_reads_a_cookie(): void
    {
        $app = new EorbahAPI();
        $app->get('/key', fn (#[Security(APIKeyCookie::class)] string $key) => $key);

        $response = (new TestClient($app))->get('/key', ['cookies' => ['api_key' => 'secret']]);

        $this->assertSame('secret', $response->text());
    }

    #[Test]
    public function oauth2_scheme_extracts_the_bearer_token(): void
    {
        $app = new EorbahAPI();
        $app->get('/token', fn (#[Security(OAuth2::class)] string $token) => $token);

        $response = (new TestClient($app))->get('/token', [
            'headers' => ['Authorization' => 'Bearer oauth-token'],
        ]);

        $this->assertSame('oauth-token', $response->text());
    }

    #[Test]
    public function jwt_signs_and_verifies_a_payload(): void
    {
        $jwt = new JWT(str_repeat('s', 32));

        $token = $jwt->sign(['sub' => '42', 'role' => 'admin']);
        $payload = $jwt->verify($token);

        $this->assertSame('42', $payload['sub']);
        $this->assertSame('admin', $payload['role']);
    }

    #[Test]
    public function an_expired_jwt_is_rejected(): void
    {
        $jwt = new JWT(str_repeat('s', 32));
        $token = $jwt->sign(['sub' => '42'], null, ['expiresIn' => -60]);

        $this->expectException(TokenExpiredError::class);

        $jwt->verify($token);
    }

    #[Test]
    public function a_token_pair_contains_access_and_refresh_tokens(): void
    {
        $jwt = new JWT(str_repeat('s', 32));

        $pair = $jwt->tokenPair(['sub' => '42']);

        $this->assertArrayHasKey('access_token', $pair);
        $this->assertArrayHasKey('refresh_token', $pair);
        $this->assertNotSame($pair['access_token'], $pair['refresh_token']);
    }

    #[Test]
    public function nonce_generates_a_random_value(): void
    {
        $nonce = new Nonce();

        $this->assertSame(64, strlen($nonce->nonce));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $nonce->nonce);
    }
}
