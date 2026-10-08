<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class VerifyCloudflareAccess
{
    private const CERTS_CACHE_KEY = 'cf_access_certs';

    /**
     * Only let through requests that carry a valid Cloudflare Access JWT for an allowed admin email.
     *
     * Validating the token here (and not only trusting Cloudflare's edge) closes the gap of
     * someone reaching the origin directly with a forged Host header.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $email = $this->localDevEmail() ?? $this->verifiedEmail($request);

        $request->attributes->set('admin_email', $email);
        View::share('adminEmail', $email);

        return $next($request);
    }

    private function localDevEmail(): ?string
    {
        $devEmail = config('admin.dev_email');

        return app()->environment('local') && $devEmail ? $devEmail : null;
    }

    private function verifiedEmail(Request $request): string
    {
        $token = $request->header('Cf-Access-Jwt-Assertion');

        if (! $token) {
            $this->deny('missing Cf-Access-Jwt-Assertion header');
        }

        // Resolved outside the try so a certs outage surfaces as 503, not as a 403 "bad token".
        $keys = $this->keysFor($token);

        try {
            $claims = JWT::decode($token, $keys);
        } catch (Throwable $e) {
            $this->deny('invalid token: '.$e->getMessage());
        }

        $teamDomain = config('admin.cf_access_team_domain');

        if (! in_array(config('admin.cf_access_aud'), (array) ($claims->aud ?? []), true)) {
            $this->deny('unexpected aud');
        }

        if (($claims->iss ?? null) !== "https://{$teamDomain}") {
            $this->deny('unexpected iss');
        }

        $email = strtolower((string) ($claims->email ?? ''));

        if (! in_array($email, config('admin.emails'), true)) {
            $this->deny("email not allowed: {$email}");
        }

        return $email;
    }

    /**
     * Cached Access public keys. A token signed with a kid we haven't seen means Cloudflare
     * rotated its keys, so the cache is refreshed once before giving up.
     *
     * @return array<string, \Firebase\JWT\Key>
     */
    private function keysFor(string $token): array
    {
        $keys = Cache::remember(self::CERTS_CACHE_KEY, 3600, fn () => $this->fetchCerts());

        if (! array_key_exists($this->kidOf($token), JWK::parseKeySet($keys, 'RS256'))) {
            Cache::forget(self::CERTS_CACHE_KEY);
            $keys = Cache::remember(self::CERTS_CACHE_KEY, 3600, fn () => $this->fetchCerts());
        }

        return JWK::parseKeySet($keys, 'RS256');
    }

    /**
     * @return array<string, mixed> the raw JWKS (cached as plain arrays, not Key objects)
     */
    private function fetchCerts(): array
    {
        $teamDomain = config('admin.cf_access_team_domain');

        try {
            return Http::timeout(5)->get("https://{$teamDomain}/cdn-cgi/access/certs")->throw()->json();
        } catch (Throwable $e) {
            Log::error('Cloudflare Access certs unavailable: '.$e->getMessage());

            abort(503);
        }
    }

    private function kidOf(string $token): ?string
    {
        $header = json_decode(JWT::urlsafeB64Decode(explode('.', $token)[0] ?? ''), true);

        return is_array($header) ? ($header['kid'] ?? null) : null;
    }

    private function deny(string $reason): never
    {
        Log::warning("Admin access denied: {$reason}");

        abort(403);
    }
}
