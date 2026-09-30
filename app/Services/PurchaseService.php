<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Throwable;

/**
 * Everything that turns a Stripe payment into a purchase, and a purchase
 * into access.
 *
 * Two rules are enforced here and nowhere else:
 *
 *   1. Access is only ever granted for a purchase whose status is `paid`,
 *      and `paid` is only ever set from data Stripe itself has confirmed.
 *   2. Recording is idempotent. Stripe delivers webhook events at least
 *      once, so every write is keyed on the Stripe session id and guarded by
 *      a unique index rather than by a check-then-insert.
 */
class PurchaseService
{
    public function __construct(protected StripeService $stripe) {}

    /* -----------------------------------------------------------------
     | Checkout
     | ----------------------------------------------------------------- */

    /**
     * Create a Stripe Checkout Session for the course and record a pending
     * purchase so we have a local trail even before the webhook arrives.
     *
     * The course is resolved by the caller from the database, so the price
     * and the Stripe Price id both come from the server.
     */
    public function beginCheckout(User $user, Course $course): Session
    {
        $priceId = $course->stripePriceId();

        if (blank($priceId)) {
            throw new PaymentException(sprintf(
                'No Stripe Price is configured for the "%s" course. Set the matching STRIPE_*_PRICE_ID value.',
                $course->slug
            ));
        }

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'client_reference_id' => (string) $user->id,
            'customer_email' => $user->email,
            // Metadata is the bridge between Stripe and our database: it lets
            // the webhook (and the return trip) attach the payment to the
            // right user and course without trusting the browser.
            'metadata' => [
                'user_id' => (string) $user->id,
                'course_id' => (string) $course->id,
                'course_slug' => $course->slug,
            ],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel'),
        ]);

        try {
            Purchase::updateOrCreate(
                ['stripe_checkout_session_id' => $session->id],
                [
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'amount' => $course->price,
                    'currency' => $course->currency,
                    'customer_email' => $user->email,
                    'customer_name' => $user->name,
                    'status' => Purchase::STATUS_PENDING,
                ]
            );
        } catch (Throwable $e) {
            // The checkout session still carries our metadata, so the webhook
            // will be able to reconstruct the purchase. Nothing is lost.
            Log::error('Unable to store the pending purchase for a checkout session.', [
                'checkout_session_id' => $session->id,
                'course_slug' => $course->slug,
                'exception' => $e->getMessage(),
            ]);
        }

        return $session;
    }

    /**
     * Ask Stripe whether a checkout session was actually paid for, and if so
     * record it. Used by the return-from-Stripe page so the user is not told
     * to wait needlessly while a webhook is in flight.
     *
     * Returns null when Stripe has no record of the session.
     */
    public function reconcileCheckoutSession(string $sessionId): ?Purchase
    {
        try {
            $session = $this->stripe->retrieveCheckoutSession($sessionId);
        } catch (Throwable $e) {
            Log::warning('Could not retrieve a checkout session from Stripe.', [
                'checkout_session_id' => $sessionId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->recordCheckoutSession($session);
    }

    /* -----------------------------------------------------------------
     | Recording payments
     | ----------------------------------------------------------------- */

    /**
     * Persist the outcome of a Checkout Session.
     *
     * Safe to call repeatedly with the same session: the row is matched on
     * `stripe_checkout_session_id` and `paid_at` is only ever set once.
     */
    public function recordCheckoutSession(Session $session, ?string $eventId = null): ?Purchase
    {
        $user = $this->resolveUser($session);
        $course = $this->resolveCourse($session);

        if (! $user || ! $course) {
            Log::error('Received a checkout session we cannot match to a user and course.', [
                'checkout_session_id' => $session->id,
                'user_id' => $session->metadata['user_id'] ?? null,
                'course_id' => $session->metadata['course_id'] ?? null,
            ]);

            return null;
        }

        $isPaid = ($session->payment_status ?? null) === 'paid';
        $intentId = $this->stringId($session->payment_intent);

        return DB::transaction(function () use ($session, $eventId, $user, $course, $isPaid, $intentId) {
            $purchase = $this->locatePurchase($user->id, $course->id, $session->id, $intentId);

            if (! $purchase) {
                $purchase = new Purchase([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ]);
            }

            $purchase->fill([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $intentId,
                'stripe_customer_id' => $this->stringId($session->customer),
                'stripe_event_id' => $eventId ?? $purchase->stripe_event_id,
                'customer_email' => $session->customer_details?->email ?? $user->email,
                'customer_name' => $session->customer_details?->name,
                'amount' => $session->amount_total ?? $course->price,
                'currency' => strtolower((string) ($session->currency ?? $course->currency)),
            ]);

            if ($isPaid) {
                $purchase->status = Purchase::STATUS_PAID;
                // Never move a paid purchase backwards or reset its timestamp.
                $purchase->paid_at ??= now();
                $purchase->failure_reason = null;
            } elseif ($purchase->status !== Purchase::STATUS_PAID) {
                $purchase->status = Purchase::STATUS_PENDING;
            }

            $purchase->save();

            return $purchase;
        });
    }

    /**
     * Confirm a purchase from a `payment_intent.succeeded` event. Checkout
     * mode copies the session metadata onto the payment intent, so this is a
     * genuine second confirmation path rather than a guess.
     */
    public function recordPaymentIntent(PaymentIntent $intent, ?string $eventId = null): ?Purchase
    {
        $metadata = $intent->metadata ?? [];
        $user = isset($metadata['user_id']) ? User::find((int) $metadata['user_id']) : null;
        $course = isset($metadata['course_id']) ? Course::find((int) $metadata['course_id']) : null;

        $existing = Purchase::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $user || ! $course) {
            // Without metadata we can still find the row the checkout already
            // opened; anything else is not ours to record.
            return $existing;
        }

        $purchase = $existing ?: $this->locatePurchase($user->id, $course->id, null, $intent->id) ?: new Purchase;

        $purchase->fill([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'stripe_payment_intent_id' => $intent->id,
            'stripe_event_id' => $eventId ?? $purchase->stripe_event_id,
            'customer_email' => $intent->receipt_email ?? $purchase->customer_email,
            'amount' => $intent->amount_received ?: ($purchase->amount ?: $course->price),
            'currency' => strtolower((string) $intent->currency),
            'status' => Purchase::STATUS_PAID,
            'paid_at' => $purchase->paid_at ?? now(),
            'failure_reason' => null,
        ]);

        $purchase->save();

        return $purchase;
    }

    /**
     * Mark a purchase failed, but never downgrade one that already succeeded.
     */
    public function recordFailedPaymentIntent(PaymentIntent $intent): void
    {
        $metadata = $intent->metadata ?? [];
        $userId = isset($metadata['user_id']) ? (int) $metadata['user_id'] : null;
        $courseId = isset($metadata['course_id']) ? (int) $metadata['course_id'] : null;

        $purchase = Purchase::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $purchase && $userId && $courseId) {
            $purchase = $this->locatePurchase($userId, $courseId, null, $intent->id);
        }

        if (! $purchase) {
            return;
        }

        // A payment that already succeeded must not be relabelled as failed
        // by a later, contradictory event.
        if ($purchase->isPaid()) {
            return;
        }

        $purchase->fill([
            'stripe_payment_intent_id' => $intent->id,
            'status' => Purchase::STATUS_FAILED,
            'failure_reason' => $intent->last_payment_error?->message ?? 'Payment failed.',
        ])->save();
    }

    /**
     * A refund revokes access: the status leaves `paid` so the single access
     * check stops passing.
     */
    public function recordRefund(object $charge): void
    {
        $intentId = $this->stringId($charge->payment_intent);

        if (! $intentId) {
            return;
        }

        Purchase::where('stripe_payment_intent_id', $intentId)
            ->update([
                'status' => Purchase::STATUS_REFUNDED,
                'refunded_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Mark an abandoned session so it stops looking "in progress" forever.
     */
    public function markSessionStatus(string $sessionId, string $status): void
    {
        Purchase::where('stripe_checkout_session_id', $sessionId)
            ->where('status', Purchase::STATUS_PENDING)
            ->update(['status' => $status, 'updated_at' => now()]);
    }

    /* -----------------------------------------------------------------
     | Webhook dispatch
     | ----------------------------------------------------------------- */

    /**
     * Handle one verified Stripe event.
     *
     * @param  object  $object  The event payload object.
     */
    public function handleEvent(object $object, string $type, string $eventId): void
    {
        match ($type) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => $this->recordCheckoutSession($object, $eventId),

            'checkout.session.expired' => $this->markSessionStatus($object->id, Purchase::STATUS_EXPIRED),

            'payment_intent.succeeded' => $this->recordPaymentIntent($object, $eventId),

            'payment_intent.payment_failed' => $this->recordFailedPaymentIntent($object),

            'charge.refunded' => $this->recordRefund($object),

            // Deliberately inert: disputes and other operational events are
            // logged for a human to review rather than acted on automatically.
            default => Log::info('Ignored Stripe webhook event.', ['type' => $type, 'event_id' => $eventId]),
        };
    }

    /* -----------------------------------------------------------------
     | Helpers
     | ----------------------------------------------------------------- */

    /**
     * Has this user already paid for this course? Used to stop a second
     * checkout for something they own.
     */
    public function alreadyPurchased(User $user, Course $course): bool
    {
        return $user->hasPurchased($course);
    }

    protected function resolveUser(Session $session): ?User
    {
        $id = $session->metadata['user_id'] ?? $session->client_reference_id ?? null;

        return $id ? User::find((int) $id) : null;
    }

    protected function resolveCourse(Session $session): ?Course
    {
        if ($id = ($session->metadata['course_id'] ?? null)) {
            return Course::find((int) $id);
        }

        $slug = $session->metadata['course_slug'] ?? null;

        return $slug ? Course::where('slug', $slug)->first() : null;
    }

    /**
     * Find the purchase row an event belongs to, without ever creating a
     * second one for the same payment.
     *
     * Stripe does not deliver `checkout.session.completed` and
     * `payment_intent.succeeded` in a guaranteed order, and either one can
     * arrive more than once. So we anchor on the strongest reference we have
     * and fall back to the row `beginCheckout()` already opened:
     *
     *   1. the checkout session id, if the event gives us one;
     *   2. the payment intent id;
     *   3. an unresolved `pending` row for this user and course — one that
     *      is not yet tied to a different payment.
     *
     * Step 3 is deliberately conservative. When the caller knows the checkout
     * session id we only accept a row that has no session id yet, so an
     * event for a second session can never hijack the first session's row.
     * A payment intent event, which carries no session id, is allowed to
     * claim the row checkout opened even though that row already has a
     * session id — that is precisely how the two get linked.
     *
     * Returning null means "this is a payment we have not seen before" and
     * the caller may insert.
     */
    protected function locatePurchase(
        int $userId,
        int $courseId,
        ?string $sessionId = null,
        ?string $intentId = null
    ): ?Purchase {
        if ($sessionId) {
            $purchase = Purchase::where('stripe_checkout_session_id', $sessionId)->first();

            if ($purchase) {
                return $purchase;
            }
        }

        if ($intentId) {
            $purchase = Purchase::where('stripe_payment_intent_id', $intentId)->first();

            if ($purchase) {
                return $purchase;
            }
        }

        $query = Purchase::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereNull('stripe_payment_intent_id')
            ->where('status', Purchase::STATUS_PENDING);

        if ($sessionId) {
            $query->whereNull('stripe_checkout_session_id');
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * Stripe returns either a plain id or an expanded object depending on
     * the API version and the expansion we asked for.
     */
    protected function stringId(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return is_string($value) ? $value : ($value->id ?? null);
    }
}
