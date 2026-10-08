<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Access
    |--------------------------------------------------------------------------
    |
    | The admin panel sits behind a Cloudflare Access application. Every request
    | carries a signed JWT (Cf-Access-Jwt-Assertion) that the VerifyCloudflareAccess
    | middleware validates against the team's public certs.
    |
    */

    'cf_access_team_domain' => env('CF_ACCESS_TEAM_DOMAIN'),

    'cf_access_aud' => env('CF_ACCESS_AUD'),

    // Second line of defence in case the Access policy is ever made too broad.
    'emails' => array_values(array_filter(array_map(
        fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', ''))
    ))),

    // Only honoured when APP_ENV=local, so the panel can be used without Cloudflare in dev.
    'dev_email' => env('ADMIN_DEV_EMAIL'),

];
