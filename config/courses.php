<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout Session
    |--------------------------------------------------------------------------
    |
    | How long a Stripe Checkout Session remains open after it is created.
    | Stripe requires this to be between 30 minutes and 24 hours.
    |
    */
    'checkout_expiry_minutes' => (int) env('COURSES_CHECKOUT_EXPIRY_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Verification Code Resend Cooldown
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before allowing a new verification code to be sent.
    | Applies to registration, login (unverified), and the resend form.
    |
    */
    'code_resend_cooldown_seconds' => (int) env('COURSES_CODE_RESEND_COOLDOWN', 60),

    /*
    |--------------------------------------------------------------------------
    | Success Page Auto-Refresh
    |--------------------------------------------------------------------------
    |
    | How often the success page polls Stripe for payment confirmation.
    | After the maximum attempts, it stops refreshing and shows a final message.
    |
    */
    'success_refresh_interval_seconds' => (int) env('COURSES_SUCCESS_REFRESH_INTERVAL', 3),
    'success_refresh_max_attempts' => (int) env('COURSES_SUCCESS_REFRESH_MAX_ATTEMPTS', 10),

    /*
    |--------------------------------------------------------------------------
    | Unverified Student Pruning
    |--------------------------------------------------------------------------
    |
    | Days after which an unverified student with no purchases is deleted.
    | Run via the `students:prune-unverified` scheduled command.
    |
    */
    'prune_unverified_days' => (int) env('COURSES_PRUNE_UNVERIFIED_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Terms and Conditions
    |--------------------------------------------------------------------------
    |
    | Version string for the terms the customer agrees to at checkout.
    | Increment this when the wording changes so you know which version
    | a customer accepted.
    |
    */
    'terms_version' => env('COURSES_TERMS_VERSION', '1.0'),

    /*
    |--------------------------------------------------------------------------
    | Consent Wording
    |--------------------------------------------------------------------------
    |
    | The text shown on the checkout review page. Links to /our-policy.
    | Make this easy to change without editing a Blade file.
    |
    */
    'consent_text' => 'I agree to the <a href="/our-policy" target="_blank" class="text-primary text-decoration-underline">Terms and Refund Policy</a>. I understand I get immediate access to digital content, so I lose my right to cancel within 14 days once access begins.',

];
