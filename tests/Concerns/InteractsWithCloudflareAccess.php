<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Signs Cloudflare Access JWTs with a throwaway RSA key and fakes the Access certs endpoint,
 * so admin panel tests exercise the real VerifyCloudflareAccess middleware.
 */
trait InteractsWithCloudflareAccess
{
    protected const ADMIN_HOST = 'http://admin.portugalbus.test';

    protected const ADMIN_EMAIL = 'admin@portugal-bus.com';

    protected const CF_TEAM_DOMAIN = 'portugalbus.cloudflareaccess.com';

    protected const CF_AUD = 'test-aud';

    protected \OpenSSLAsymmetricKey $accessPrivateKey;

    protected function setUpCloudflareAccess(): void
    {
        $this->accessPrivateKey = $this->newRsaKey();

        config([
            'admin.cf_access_team_domain' => self::CF_TEAM_DOMAIN,
            'admin.cf_access_aud' => self::CF_AUD,
            'admin.emails' => [self::ADMIN_EMAIL],
            'admin.dev_email' => null,
        ]);

        $this->fakeAccessCerts(['test-kid' => $this->accessPrivateKey]);
        $this->withoutVite();
    }

    /**
     * @param  array<string, \OpenSSLAsymmetricKey>  $keysByKid
     */
    protected function fakeAccessCerts(array $keysByKid): void
    {
        Http::fake([
            'https://'.self::CF_TEAM_DOMAIN.'/cdn-cgi/access/certs' => Http::response([
                'keys' => collect($keysByKid)->map(fn ($key, $kid) => $this->jwkFor($key, $kid))->values()->all(),
            ]),
        ]);
    }

    protected function accessToken(array $claims = [], string $kid = 'test-kid', ?\OpenSSLAsymmetricKey $key = null): string
    {
        $payload = array_merge([
            'aud' => [self::CF_AUD],
            'iss' => 'https://'.self::CF_TEAM_DOMAIN,
            'email' => self::ADMIN_EMAIL,
            // php-jwt checks exp against the real clock (time()), not Carbon's travelled one.
            'iat' => time(),
            'exp' => time() + 3600,
        ], $claims);

        return JWT::encode($payload, $key ?? $this->accessPrivateKey, 'RS256', $kid);
    }

    protected function asAdmin(): static
    {
        return $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken());
    }

    protected function newRsaKey(): \OpenSSLAsymmetricKey
    {
        return openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    }

    private function jwkFor(\OpenSSLAsymmetricKey $key, string $kid): array
    {
        $rsa = openssl_pkey_get_details($key)['rsa'];

        return [
            'kid' => $kid,
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => rtrim(strtr(base64_encode($rsa['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($rsa['e']), '+/', '-_'), '='),
        ];
    }
}
