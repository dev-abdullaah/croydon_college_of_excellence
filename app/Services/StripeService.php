<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Thin, testable wrapper around the Stripe PHP SDK.
 *
 * Everything Stripe-related goes through here so that no controller or
 * service has to worry about client construction, and so the SDK can be
 * swapped for a fake in tests.
 */
class StripeService
{
    private ?StripeClient $client = null;

    /**
     * The configured Stripe client.
     *
     * Throws when no secret key is configured rather than silently issuing
     * requests that would fail with an opaque authentication error.
     */
    public function client(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        $secret = config('stripe.secret');

        if (blank($secret)) {
            throw new PaymentException(
                'Stripe is not configured. Set STRIPE_SECRET in your .env file.'
            );
        }

        $options = ['api_key' => $secret];

        if (filled($version = config('stripe.api_version'))) {
            $options['stripe_version'] = $version;
        }

        return $this->client = new StripeClient($options);
    }

    /**
     * Create a hosted Checkout Session for a course.
     *
     * The line item is built from the server side Stripe Price id, never
     * from anything supplied by the browser.
     */
    public function createCheckoutSession(array $params): Session
    {
        return $this->client()->checkout->sessions->create($params);
    }

    /**
     * Retrieve a Checkout Session straight from Stripe. The returned object
     * is the source of truth about whether money actually moved.
     */
    public function retrieveCheckoutSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * Verify and decode an inbound webhook payload.
     *
     * @throws SignatureVerificationException
     */
    public function constructEvent(string $payload, string $signature, int $tolerance): Event
    {
        return Webhook::constructEvent($payload, $signature, $this->webhookSecret(), $tolerance);
    }

    public function webhookSecret(): string
    {
        $secret = config('stripe.webhook.secret');

        if (blank($secret)) {
            throw new PaymentException(
                'Stripe webhook secret is not configured. Set STRIPE_WEBHOOK_SECRET in your .env file.'
            );
        }

        return $secret;
    }

    /**
     * True when the configured keys are live keys. Used to block test-mode
     * behaviour on a production deployment (and vice versa).
     */
    public function usingLiveKeys(): bool
    {
        return str_starts_with((string) config('stripe.secret'), 'sk_live_')
            || str_starts_with((string) config('stripe.key'), 'pk_live_');
    }
}
