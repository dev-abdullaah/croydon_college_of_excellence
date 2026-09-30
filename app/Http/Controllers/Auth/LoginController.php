<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
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

        if ($user->hasVerifiedEmail() === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->put('verification.email', $user->email);

            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')
                ->with('info', 'Please confirm your email address first. We have sent you a fresh link.');
        }

        // New session id on privilege change: prevents session fixation.
        $request->session()->regenerate();

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
