<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithCloudflareAccess;
use Tests\TestCase;

class CloudflareAccessTest extends TestCase
{
    use InteractsWithCloudflareAccess;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpCloudflareAccess();
    }

    public function test_valid_token_grants_access(): void
    {
        $this->asAdmin()->get(self::ADMIN_HOST.'/')
            ->assertOk()
            ->assertSee(self::ADMIN_EMAIL);
    }

    public function test_missing_header_is_forbidden(): void
    {
        $this->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_bad_signature_is_forbidden(): void
    {
        $token = $this->accessToken(key: $this->newRsaKey());

        $this->withHeader('Cf-Access-Jwt-Assertion', $token)->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_wrong_audience_is_forbidden(): void
    {
        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(['aud' => ['other']]))
            ->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_wrong_issuer_is_forbidden(): void
    {
        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(['iss' => 'https://evil.cloudflareaccess.com']))
            ->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_expired_token_is_forbidden(): void
    {
        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(['exp' => now()->subMinute()->timestamp]))
            ->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_unlisted_email_is_forbidden(): void
    {
        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(['email' => 'x@y.com']))
            ->get(self::ADMIN_HOST.'/')->assertForbidden();
    }

    public function test_email_match_is_case_insensitive(): void
    {
        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(['email' => 'Admin@Portugal-Bus.com']))
            ->get(self::ADMIN_HOST.'/')->assertOk();
    }

    public function test_unknown_kid_refetches_certs_once(): void
    {
        // Warm the cache with the current key, then rotate: Cloudflare now serves only the new key.
        $this->asAdmin()->get(self::ADMIN_HOST.'/')->assertOk();

        $rotated = $this->newRsaKey();
        Http::swap(new \Illuminate\Http\Client\Factory);
        $this->fakeAccessCerts(['new-kid' => $rotated]);

        $this->withHeader('Cf-Access-Jwt-Assertion', $this->accessToken(kid: 'new-kid', key: $rotated))
            ->get(self::ADMIN_HOST.'/')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_certs_unavailable_without_cache_is_503(): void
    {
        Cache::flush();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['*' => Http::response('down', 500)]);

        $this->asAdmin()->get(self::ADMIN_HOST.'/')->assertStatus(503);
    }

    public function test_dev_email_bypass_works_in_local(): void
    {
        $this->app['env'] = 'local';
        config(['admin.dev_email' => 'dev@portugal-bus.com']);

        $this->get(self::ADMIN_HOST.'/')->assertOk()->assertSee('dev@portugal-bus.com');
    }

    public function test_dev_email_bypass_is_ignored_outside_local(): void
    {
        $this->app['env'] = 'production';
        config(['admin.dev_email' => 'dev@portugal-bus.com']);

        $this->get(self::ADMIN_HOST.'/')->assertForbidden();
    }
}
