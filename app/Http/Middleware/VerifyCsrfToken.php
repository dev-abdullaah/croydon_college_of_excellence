<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * Stripe cannot send a CSRF token. Requests to this path are instead
     * authenticated by verifying Stripe's `Stripe-Signature` header against
     * the endpoint's signing secret, which is strictly stronger.
     *
     * @var array<int, string>
     */
    protected $except = [
        'stripe/webhook',
    ];
}
