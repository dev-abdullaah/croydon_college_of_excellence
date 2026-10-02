<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\IntendedCourse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Minimal session based sign in.
 *
 * The project ships without any authentication UI, so this is the smallest
 * secure flow that lets a purchase be attached to a real user. It is built on
 * Laravel's session guard - there is no token in a URL, and no access is
 * ever granted from a query string parameter.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('website.pages.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Deliberately vague so the form cannot be used to discover which
            // email addresses have accounts.
            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        /*
         | A correct password is not proof of the email address, so an
         | unverified account is signed straight back out. Auth::attempt has
         | already put a live session in place by this point, so it has to be
         | undone rather than merely not started.
         |
         | This is the difference between "your password is right" and "you
         | own this inbox". A fresh link goes out on the way, because the
         | likeliest reason somebody is here is that the original expired or
         | landed in spam.
         */
        $user = Auth::user();

        /*
         | The course this person came to buy lives in the session, and the
         | unverified branch below empties the session. Read it first.
         */
        $intendedSlug = IntendedCourse::peek();

        if ($user->hasVerifiedEmail() === false) {
            Auth::guard('web')->logout();

            /*
             | invalidate() throws the whole session away, which is the right
             | thing to do on the way out of a half-authenticated state: it
             | clears the old session id, everything that was put in it, and
             | the intended URL. The course the visitor actually came for is
             | not part of that clean-up - it is the one piece of session data
             | that is about their next purchase rather than about this sign-in
             | - so it goes back in afterwards.
             */
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('verification.email', $user->email);

            if ($intendedSlug !== null) {
                IntendedCourse::remember($intendedSlug);
            }

            $sent = $user->sendVerificationCodeIfDue();

            if (! $sent) {
                return redirect()->route('verification.notice')
                    ->with('info', 'A code was sent a moment ago. Please check your inbox and spam folder, or try again in a minute.');
            }

            return redirect()->route('verification.notice')
                ->with('info', 'Please confirm your email address first. We have sent you a fresh code.');
        }

        // New session id on privilege change: prevents session fixation.
        $request->session()->regenerate();

        /*
         | A returning customer is mid-purchase, so the review page they were
         | heading for beats the dashboard. The course is re-resolved and
         | checked against their purchases, so this cannot send somebody to pay
         | for something they already own.
         |
         | Falling through to redirect()->intended() keeps today's behaviour
         | for everyone else, including the case where Laravel remembered where
         | they were bounced from.
         */
        if ($course = IntendedCourse::resolveIfUnowned($user)) {
            return redirect()->route('checkout.review', $course)
                ->with('success', 'Welcome back, '.$user->name.'!');
        }

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, '.$user->name.'!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }
}
