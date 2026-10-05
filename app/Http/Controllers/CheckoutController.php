<?php

namespace App\Http\Controllers;

use App\Exceptions\AlreadyPurchasedException;
use App\Exceptions\PaymentException;
use App\Models\Course;
use App\Models\Purchase;
use App\Services\PurchaseService;
use App\Support\IntendedCourse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    /**
     * Course detail page: what is included, the price, and the buy button.
     *
     * The buy button here is a plain link to `checkout.start`, which is what
     * lets a guest begin a purchase instead of being pushed at a login form
     * with the course already forgotten. Everything the page shows - the
     * price, the feature list, whether the visitor already owns it - is read
     * from the database.
     */
    public function show(Request $request, Course $course): View|RedirectResponse
    {
        if ($request->user()?->hasPurchased($course)) {
            return redirect()->route('learn.index', $course);
        }

        return view('website.pages.courses.show', [
            'course' => $course,
        ]);
    }

    /**
     * New entry point: remembers the chosen course and routes the visitor to
     * the right step (register, verify, review, or dashboard).
     *
     * Public, rate limited, never creates a payment.
     */
    public function start(Request $request, Course $course): RedirectResponse
    {
        abort_if(! $course->is_active, 404);

        // Remember the slug
        IntendedCourse::remember($course->slug);

        // Guest -> register page
        if (! $request->user()) {
            return redirect()->route('register');
        }

        $user = $request->user();

        // Signed in but unverified -> verify page
        if (! $user->hasVerifiedEmail()) {
            // Set verification.email for the verify page
            $request->session()->put('verification.email', $user->email);

            // Send a code if none was sent in the last 60 seconds
            $cooldown = config('courses.code_resend_cooldown_seconds', 60);
            if ($user->verification_code_sent_at === null ||
                $user->verification_code_sent_at->addSeconds($cooldown)->isPast()) {
                $user->sendEmailVerificationCodeNotification();
            }

            return redirect()->route('verification.notice');
        }

        // Signed in, verified, already owns the course
        if ($user->hasPurchased($course)) {
            return redirect()->route('dashboard')
                ->with('info', 'You already own '.$course->name.' - it is waiting in your account.');
        }

        // Signed in, verified, does not own it -> checkout.review
        return redirect()->route('checkout.review', $course);
    }

    /**
     * Review page: shows the course, price, features, consent tick box,
     * and a Pay button that POSTs to checkout.store.
     */
    public function review(Request $request, Course $course): View|RedirectResponse
    {
        abort_if(! $course->is_active, 404);

        if ($request->user()->hasPurchased($course)) {
            return redirect()->route('dashboard')
                ->with('info', 'You already own '.$course->name.'.');
        }

        return view('website.pages.checkout.review', [
            'course' => $course,
            'consentText' => config('courses.consent_text'),
        ]);
    }

    /**
     * Start a Stripe Checkout Session.
     *
     * The course arrives through route model binding, so the slug comes
     * from the URL and everything else (price, Stripe Price id) is read from
     * the database on the server. Nothing about the amount is read from the
     * request body.
     */
    public function store(Request $request, Course $course): RedirectResponse
    {
        abort_if(! $course->is_active, 404);

        $user = $request->user();

        if ($user->hasPurchased($course)) {
            return redirect()->route('dashboard')
                ->with('info', 'You already own '.$course->name.'.');
        }

        /*
         | The tick box is what the customer is agreeing to, and it is required
         | rather than advisory: a session created without it would record an
         | acceptance that never happened, on a purchase of digital content
         | that cannot be returned.
         |
         | A failure is sent back to the review page explicitly. Left to itself
         | Laravel redirects a failed POST to wherever the browser was last,
         | which for somebody who arrived at the review page and pressed Pay is
         | usually nothing at all - they would land on the front page with an
         | error message they never see, having lost the course they were
         | buying. Naming the destination keeps them on the one page that has
         | the box to tick.
         */
        try {
            $request->validate([
                'consent' => ['required', 'accepted'],
            ], [
                'consent.accepted' => 'Please tick the box to agree to the terms before paying.',
            ]);
        } catch (ValidationException $e) {
            throw (ValidationException::withMessages($e->errors()))
                ->redirectTo(route('checkout.review', $course));
        }

        try {
            $session = $this->purchases->beginCheckout($user, $course, termsAccepted: true);
        } catch (AlreadyPurchasedException $e) {
            // The pending session turned out to have been paid on another
            // page, so there is nothing left to charge for. Take them to the
            // course they now own rather than to an error.
            return redirect()->route('dashboard')
                ->with('success', 'Payment received. '.$course->name.' is ready below.');
        } catch (PaymentException $e) {
            Log::error('Checkout could not be started.', [
                'course_slug' => $course->slug,
                'reason' => $e->getMessage(),
            ]);

            return redirect()->route('checkout.review', $course)
                ->with('error', 'Online payments are temporarily unavailable. Please contact us on 07405 073764.');
        } catch (Throwable $e) {
            Log::error('Stripe checkout session creation failed.', [
                'course_slug' => $course->slug,
                'exception' => $e->getMessage(),
            ]);

            return redirect()->route('checkout.review', $course)
                ->with('error', 'We could not open the payment page. Please try again in a moment.');
        }

        // Remembered only so the "payment cancelled" page can offer a retry
        // for the right course. Never used to decide anything.
        $request->session()->put('checkout.course', $course->slug);

        return redirect()->away($session->url);
    }

    /**
     * Landing page Stripe returns the customer to after a successful payment.
     *
     * Reaching this URL grants nothing. The session id is used to ask Stripe
     * what actually happened, and the purchase is only shown as confirmed
     * when Stripe says the money arrived for this user.
     */
    public function success(Request $request): View|RedirectResponse
    {
        // A real Stripe session id looks like cs_test_a1B2c3D4e5F6g7H8i9J0kL1.
        // The shape check is a cheap guard against nonsense being pushed
        // into the Stripe lookup; it is not the security boundary, which is
        // asking Stripe about the session and matching it to this user.
        $request->validate([
            'session_id' => ['required', 'string', 'regex:/^cs_[A-Za-z0-9_]+$/'],
        ]);

        $sessionId = $request->string('session_id')->toString();
        $user = $request->user();

        $request->session()->forget('checkout.course');

        // Only ever show a purchase that belongs to the signed-in student, so a
        // session id pasted from someone else's browser reveals nothing.
        $purchase = Purchase::where('stripe_checkout_session_id', $sessionId)
            ->where('student_id', $user->id)
            ->first();

        if (! $purchase || ! $purchase->isPaid()) {
            // The webhook may still be in flight. Ask Stripe directly and
            // record the result if it has landed.
            $purchase = $this->purchases->reconcileCheckoutSession($sessionId);

            if ($purchase && $purchase->student_id !== $user->id) {
                $purchase = null;
            }
        }

        $purchase = $purchase?->fresh('course');

        /*
         | Confirmed, so this page has done its job. My Account is where the
         | course actually is, and the slug travels as a session value rather
         | than a query string so the dashboard can highlight it. Note what is
         | *not* said: whether a receipt was emailed. Whether Stripe sends one
         | is an account setting on their side, and promising something we do
         | not control is how people end up emailing us to ask where it is.
         */
        if ($purchase && $purchase->isPaid()) {
            IntendedCourse::forget();

            return redirect()->route('dashboard')
                ->with('success', 'Payment received. Your course is ready below.')
                ->with('highlight_course', $purchase->course->slug);
        }

        /*
         | Not confirmed yet. The webhook can be seconds behind a card payment,
         | so the sensible thing is to keep asking Stripe for a short while
         | rather than tell somebody who has just paid that we cannot see it.
         |
         | `attempt` counts the polls. It is a counter and nothing more: the
         | page grants nothing on any value of it, and it is clamped so a
         | hand-typed query string cannot spin the browser against Stripe.
         */
        $maxAttempts = max(1, (int) config('courses.success_refresh_max_attempts', 10));
        $attempt = max(1, min($maxAttempts, (int) $request->query('attempt', 1)));

        return view('website.pages.checkout.success', [
            'purchase' => null,
            'course' => $purchase?->course,
            'attempt' => $attempt,
            'maxAttempts' => $maxAttempts,
            'refreshInterval' => max(1, (int) config('courses.success_refresh_interval_seconds', 3)),
        ]);
    }

    /**
     * Landing page Stripe returns the customer to after a cancelled payment.
     *
     * Nothing was charged, so nothing is granted. The slug remembered at
     * checkout time is read *before* it is cleared, and is used only to
     * offer a retry link back to the course they were buying.
     */
    public function cancel(Request $request): View
    {
        $slug = $request->session()->pull('checkout.course');

        $course = $slug
            ? Course::where('slug', $slug)->where('is_active', true)->first()
            : null;

        return view('website.pages.checkout.cancel', [
            'course' => $course,
        ]);
    }
}
