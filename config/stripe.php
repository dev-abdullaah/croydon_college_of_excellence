<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe Publishable Key
    |--------------------------------------------------------------------------
    |
    | Safe to expose to the browser. Only ever the *publishable* key
    | (pk_test_... / pk_live_...). Test keys are used by default.
    |
    */

    'key' => env('STRIPE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Secret Key
    |--------------------------------------------------------------------------
    |
    | Server side only. Never expose this value in a view, a log line or a
    | client side script. Use a sk_test_... key while testing and swap it
    | for sk_live_... (together with the matching webhook secret) when you
    | are ready to take real payments.
    |
    */

    'secret' => env('STRIPE_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhooks
    |--------------------------------------------------------------------------
    |
    | `secret` is the signing secret Stripe shows you when you create a
    | webhook endpoint. Every inbound event is verified against it before it
    | is trusted, so it must never be left empty in production.
    |
    */

    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Price IDs
    |--------------------------------------------------------------------------
    |
    | Each course is sold through its own Stripe Price so the amount and
    | currency are defined in Stripe and can never be tampered with from
    | the browser. Create two one-off (one-time) prices in your Stripe
    | dashboard, both in GBP:
    |
    |   Life in the UK Course .... £99.00  ->  STRIPE_COURSE_PRICE_ID
    |   24 Mock Tests ............ £49.00  ->  STRIPE_MOCK_TEST_PRICE_ID
    |
    | The keys below are matched against the `stripe_price_key` column on
    | the `courses` table.
    |
    */

    'prices' => [
        'course' => env('STRIPE_COURSE_PRICE_ID'),
        'mock_tests' => env('STRIPE_MOCK_TEST_PRICE_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Currency
    |--------------------------------------------------------------------------
    |
    | Every course is priced in British pounds. Stripe expects the currency
    | in lower case ("gbp").
    |
    */

    'currency' => env('STRIPE_CURRENCY', 'gbp'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    |
    | Pinned so the integration keeps behaving predictably when Stripe
    | releases a new default version. Remove the key to always track the
    | latest version bundled with stripe/stripe-php.
    |
    */

    'api_version' => env('STRIPE_API_VERSION'),

];
