<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Course;
use App\Models\Purchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class CheckoutController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    /**
     * Course detail page: what is included, the price, and the buy button.
     */
    public function show(Request $request, Course $course): View|RedirectResponse
    {
        abort_if(! $course->is_active, 404);

        if ($request->user()?->hasPurchased($course)) {
            return redirect()->route('dashboard')
                ->with('info', 'You already own '.$course->name.' - it is waiting in your account.');
        }

        return view('website.pages.courses.show', [
            'course' => $course,
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

        if ($request->user()->hasPurchased($course)) {
            return redirect()->route('dashboard')
                ->with('info', 'You already own '.$course->name.'.');
        }

        try {
            $session = $this->purchases->beginCheckout($request->user(), $course);
        } catch (PaymentException $e) {
            Log::error('Checkout could not be started.', [
                'course_slug' => $course->slug,
                'reason' => $e->getMessage(),
            ]);

            return back()->with('error', 'Online payments are temporarily unavailable. Please contact us on 07405 073764.');
        } catch (Throwable $e) {
            Log::error('Stripe checkout session creation failed.', [
                'course_slug' => $course->slug,
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'We could not open the payment page. Please try again in a moment.');
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
    public function success(Request $request): View
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

        // Only ever show a purchase that belongs to the signed-in user, so a
        // session id pasted from someone else's browser reveals nothing.
        $purchase = Purchase::where('stripe_checkout_session_id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (! $purchase || ! $purchase->isPaid()) {
            // The webhook may still be in flight. Ask Stripe directly and
            // record the result if it has landed.
            $purchase = $this->purchases->reconcileCheckoutSession($sessionId);

            if ($purchase && $purchase->user_id !== $user->id) {
                $purchase = null;
            }
        }

        $purchase = $purchase?->fresh('course');

        return view('website.pages.checkout.success', [
            'purchase' => $purchase?->isPaid() ? $purchase : null,
            'course' => $purchase?->course,
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
