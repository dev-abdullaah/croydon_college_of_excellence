<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\StripeWebhookEvent;
use App\Services\PurchaseService;
use App\Services\StripeService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Throwable;

/**
 * Stripe webhook receiver.
 *
 * The signature is verified against the endpoint's signing secret before the
 * body is parsed, and every event id is recorded exactly once so a replayed
 * or duplicated delivery cannot produce a second purchase.
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripeService $stripe,
        protected PurchaseService $purchases,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $secret = $this->stripe->webhookSecret();
        } catch (PaymentException $e) {
            // Failing loudly beats silently accepting unverified events.
            Log::error('Stripe webhook rejected: no signing secret configured.');

            return response()->json(['message' => 'Webhook is not configured.'], 500);
        }

        $signature = $request->header('Stripe-Signature');

        if (blank($signature)) {
            return response()->json(['message' => 'Missing Stripe-Signature header.'], 400);
        }

        try {
            $event = $this->stripe->constructEvent(
                $request->getContent(),
                $signature,
                (int) config('stripe.webhook.tolerance', 300),
            );
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed.', [
                'reason' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 403);
        } catch (Throwable $e) {
            Log::error('Stripe webhook payload could not be decoded.', ['reason' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // Claim the event. The unique index on event_id makes this the single
        // point where a duplicate delivery is detected, with no race window
        // between "have I seen this?" and "record that I have".
        try {
            $record = StripeWebhookEvent::create([
                'event_id' => $event->id,
                'type' => $event->type,
                'livemode' => (bool) $event->livemode,
                'payload' => $request->getContent(),
            ]);
        } catch (QueryException $e) {
            Log::info('Duplicate Stripe webhook event ignored.', [
                'event_id' => $event->id,
                'type' => $event->type,
            ]);

            return response()->json(['message' => 'Already processed.'], 200);
        }

        try {
            $this->purchases->handleEvent($event->data->object, $event->type, $event->id);
        } catch (Throwable $e) {
            // Release the claim so Stripe's retry can make progress, then let
            // Stripe see a 500 and re-deliver.
            $record->delete();

            Log::error('Stripe webhook handler failed.', [
                'event_id' => $event->id,
                'type' => $event->type,
                'exception' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Webhook handler failed.'], 500);
        }

        $record->forceFill(['processed_at' => now()])->save();

        return response()->json(['message' => 'OK']);
    }
}
