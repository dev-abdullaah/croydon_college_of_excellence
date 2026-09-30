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

        // New session id on privilege change: prevents session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))
            ->with('success', 'Welcome back, '.Auth::user()->name.'!');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }
}
