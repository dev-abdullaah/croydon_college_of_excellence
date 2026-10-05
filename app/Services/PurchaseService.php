<?php

namespace App\Services;

use App\Exceptions\AlreadyPurchasedException;
use App\Exceptions\PaymentException;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
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
     *
     * Two things stop one purchase attempt becoming two payments.
     *
     * The first is reuse. A pending purchase that already has a Stripe session
     * inside its checkout lifetime is asked about at Stripe before a new one
     * is opened, and an answer of "still open" hands back the URL they were
     * already on. Opening a second session for the same attempt creates two
     * ways to pay for one course, and the customer who completes the wrong
     * one has paid for something they can no longer reach.
     *
     * The second is the lock. Reuse is a read followed by a write, so two
     * clicks arriving together can both see "no open session" and both create
     * one. The lock makes the second request wait, and makes the first one to
     * arrive do the work.
     */
    public function beginCheckout(Student $student, Course $course, bool $termsAccepted = false): Session
    {
        $priceId = $course->stripePriceId();

        if (blank($priceId)) {
            throw new PaymentException(sprintf(
                'No Stripe Price is configured for the "%s" course. Set the matching STRIPE_*_PRICE_ID value.',
                $course->slug
            ));
        }

        $lock = Cache::lock($this->checkoutLockKey($student, $course), 10);

        if (! $lock->get()) {
            /*
             | A session is being opened for this customer and course right
             | now. Refusing is the safe answer: waiting here would tie up a
             | web worker for a request whose whole job is to hand the browser
             | off to Stripe, and guessing would be the duplicate we are
             | trying to prevent. The customer presses the button again, by
             | which time the first request has finished and its session can be
             | reused.
             */
            throw new PaymentException(
                'A checkout for this course is already being started. Please try again in a moment.'
            );
        }

        try {
            return $this->openCheckoutSession($student, $course, $priceId, $termsAccepted);
        } finally {
            $lock->release();
        }
    }

    /**
     * The work of beginCheckout, with the lock already held.
     */
    protected function openCheckoutSession(Student $student, Course $course, string $priceId, bool $termsAccepted): Session
    {
        if ($existing = $this->reusableSession($student, $course)) {
            return $existing;
        }

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'payment',
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'client_reference_id' => (string) $student->id,
            'customer_email' => $student->email,
            /*
             | How long Stripe keeps this page open for the customer. Stripe
             | rejects anything outside 30 minutes to 24 hours, so the setting
             | is clamped rather than trusted: a mistyped config value should
             | be corrected here, not turned into a failed payment at the one
             | moment somebody is trying to buy something.
             */
            'expires_at' => now()->addMinutes($this->checkoutExpiryMinutes())->getTimestamp(),
            /*
             | Metadata is the bridge between Stripe and our database: it lets
             | the webhook (and the return trip) attach the payment to the
             | right student and course without trusting the browser.
             */
            'metadata' => [
                'student_id' => (string) $student->id,
                'course_id' => (string) $course->id,
                'course_slug' => $course->slug,
            ],
            'payment_intent_data' => [
                /*
                 | Ask Stripe to send the receipt to the address the payment is
                 | tied to. Specifying receipt_email sends one in live mode
                 | whatever the account's own email settings say, which is what
                 | we want: somebody who has just paid a hundred pounds and
                 | heard nothing turns into a support call.
                 */
                'receipt_email' => $student->email,
            ],
            'success_url' => route('checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('checkout.cancel'),
        ]);

        $this->recordPendingPurchase($student, $course, $session->id, $termsAccepted);

        return $session;
    }

    /**
     * A pending session for this customer and course that is still worth
     * sending them back to, or null when a new one is needed.
     *
     * Only the newest pending row counts, and only if it was opened inside the
     * current checkout lifetime. Older rows are ignored on purpose: their
     * session may have expired at Stripe, and sending somebody to Stripe's
     * expired-session page is a dead end with no way forward except back.
     *
     * Stripe is asked rather than the local row trusted, because the local row
     * is our guess at what happened and Stripe is the record of it. Three
     * answers matter:
     *
     *   - still open: hand back the same URL, so one attempt stays one attempt;
     *   - paid: the customer finished somewhere else, so record it through the
     *     normal idempotent path and report that there is nothing to pay for;
     *   - anything else (expired, complete but unpaid): fall through and open a
     *     new session.
     */
    protected function reusableSession(Student $student, Course $course): ?Session
    {
        $purchase = Purchase::query()
            ->where('student_id', $student->id)
            ->forCourse($course)
            ->where('status', Purchase::STATUS_PENDING)
            ->whereNotNull('stripe_checkout_session_id')
            ->where('created_at', '>=', now()->subMinutes($this->checkoutExpiryMinutes()))
            ->orderByDesc('id')
            ->first();

        if (! $purchase) {
            return null;
        }

        try {
            $session = $this->stripe->retrieveCheckoutSession($purchase->stripe_checkout_session_id);
        } catch (Throwable $e) {
            // Stripe is unreachable, or the session is gone. Neither is a
            // reason to strand the customer: open a fresh one and let them pay.
            Log::info('Could not reuse a checkout session; opening a new one.', [
                'checkout_session_id' => $purchase->stripe_checkout_session_id,
                'course_slug' => $course->slug,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }

        if (($session->payment_status ?? null) === 'paid') {
            /*
             | Already paid on this session. Recording it here uses the same
             | idempotent path the webhook uses, so a webhook arriving late
             | updates this row rather than creating a second purchase. A paid
             | session is not something to send anybody back to, which is why
             | this reports "already bought" and lets the controller take them
             | to the course.
             |
             | Paid is checked before open, and the order matters. A delayed
             | payment method can leave the session open at Stripe for a while
             | after the money has arrived, so "still open" does not by itself
             | mean there is still something to pay - read the other way round,
             | it sends somebody back to a page asking for money we have
             | already been given.
             */
            $this->recordCheckoutSession($session);

            throw new AlreadyPurchasedException('This course has already been paid for.');
        }

        if (($session->status ?? null) === 'open') {
            return $session;
        }

        return null;
    }

    /**
     * The pending row that gives us a local trail before the webhook arrives.
     */
    protected function recordPendingPurchase(Student $student, Course $course, string $sessionId, bool $termsAccepted): void
    {
        try {
            Purchase::updateOrCreate(
                ['stripe_checkout_session_id' => $sessionId],
                [
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                    'amount' => $course->price,
                    'currency' => $course->currency,
                    'customer_email' => $student->email,
                    'customer_name' => $student->name,
                    'status' => Purchase::STATUS_PENDING,
                    /*
                     | The consent is recorded the moment the tick box is
                     | accepted, before Stripe has said anything about the
                     | money, so an acceptance that leads to an abandoned or
                     | failed payment is still on file.
                     |
                     | Only set when it was actually given, and never moved
                     | afterwards: re-opening a checkout is not a fresh
                     | agreement, and overwriting the timestamp would
                     | misrepresent when the customer agreed to the terms.
                     */
                    'terms_accepted_at' => $termsAccepted ? now() : null,
                    'terms_version' => $termsAccepted ? config('courses.terms_version') : null,
                ]
            );
        } catch (Throwable $e) {
            // The checkout session still carries our metadata, so the webhook
            // will be able to reconstruct the purchase. Nothing is lost.
            Log::error('Unable to store the pending purchase for a checkout session.', [
                'checkout_session_id' => $sessionId,
                'course_slug' => $course->slug,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The lock key for one customer's attempt to buy one course.
     */
    protected function checkoutLockKey(Student $student, Course $course): string
    {
        return "checkout:{$student->id}:{$course->id}";
    }

    /**
     * How long a Checkout Session should stay open, clamped to what Stripe
     * accepts. Outside 30 minutes to 24 hours the API rejects the request.
     */
    protected function checkoutExpiryMinutes(): int
    {
        $minutes = (int) config('courses.checkout_expiry_minutes', 60);

        return max(30, min(24 * 60, $minutes));
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
        $student = $this->resolveUser($session);
        $course = $this->resolveCourse($session);

        if (! $student || ! $course) {
            Log::error('Received a checkout session we cannot match to a student and course.', [
                'checkout_session_id' => $session->id,
                'student_id' => $session->metadata['student_id'] ?? null,
                'course_id' => $session->metadata['course_id'] ?? null,
            ]);

            return null;
        }

        $isPaid = ($session->payment_status ?? null) === 'paid';
        $intentId = $this->stringId($session->payment_intent);

        return DB::transaction(function () use ($session, $eventId, $student, $course, $isPaid, $intentId) {
            $purchase = $this->locatePurchase($student->id, $course->id, $session->id, $intentId);

            if (! $purchase) {
                $purchase = new Purchase([
                    'student_id' => $student->id,
                    'course_id' => $course->id,
                ]);
            }

            $purchase->fill([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'stripe_checkout_session_id' => $session->id,
                'stripe_payment_intent_id' => $intentId,
                'stripe_customer_id' => $this->stringId($session->customer),
                'stripe_event_id' => $eventId ?? $purchase->stripe_event_id,
                'customer_email' => $session->customer_details?->email ?? $student->email,
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
        $student = isset($metadata['student_id']) ? Student::find((int) $metadata['student_id']) : null;
        $course = isset($metadata['course_id']) ? Course::find((int) $metadata['course_id']) : null;

        $existing = Purchase::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $student || ! $course) {
            // Without metadata we can still find the row the checkout already
            // opened; anything else is not ours to record.
            return $existing;
        }

        $purchase = $existing ?: $this->locatePurchase($student->id, $course->id, null, $intent->id) ?: new Purchase;

        $purchase->fill([
            'student_id' => $student->id,
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
        $studentId = isset($metadata['student_id']) ? (int) $metadata['student_id'] : null;
        $courseId = isset($metadata['course_id']) ? (int) $metadata['course_id'] : null;

        $purchase = Purchase::where('stripe_payment_intent_id', $intent->id)->first();

        if (! $purchase && $studentId && $courseId) {
            $purchase = $this->locatePurchase($studentId, $courseId, null, $intent->id);
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
     * Has this student already paid for this course? Used to stop a second
     * checkout for something they own.
     */
    public function alreadyPurchased(Student $student, Course $course): bool
    {
        return $student->hasPurchased($course);
    }

    protected function resolveUser(Session $session): ?Student
    {
        $id = $session->metadata['student_id'] ?? $session->client_reference_id ?? null;

        return $id ? Student::find((int) $id) : null;
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

        $query = Purchase::where('student_id', $userId)
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
