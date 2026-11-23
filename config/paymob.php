<?php

/**
 * Paymob Accept configuration (spec §4.8, Phase 10). Secrets live ONLY
 * in the environment; nothing here has a real default. Card payment
 * stays disabled until the owner has a merchant account, credentials
 * are configured AND a test-mode callback has been verified — the
 * owner confirmed (2026-11-23) that there is NO Paymob merchant
 * account, so v1 runs COD + bank transfer only.
 */
return [
    'api_key' => env('PAYMOB_API_KEY'),
    'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
    'integration_id' => env('PAYMOB_INTEGRATION_ID'),
    'iframe_id' => env('PAYMOB_IFRAME_ID'),
    'merchant_id' => env('PAYMOB_MERCHANT_ID'),
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),
];
