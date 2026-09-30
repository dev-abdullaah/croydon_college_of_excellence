<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service account creation.
 *
 * An account is what ties a Stripe payment to a person; the webhook matches
 * the payment back to this user through the metadata set at checkout.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('website.pages.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        /*
         | The account exists but proves nothing yet: `email_verified_at` is
         | left null, so every route behind the `verified` middleware refuses
         | it, including the dashboard.
         |
         | Firing Registered sends the verification link. It is fired by hand
         | rather than relied upon automatically because Laravel only does
         | that when a listener is subscribed; naming the event makes the
         | dependency visible, and means the email cannot quietly stop being
         | sent if that subscription is ever changed.
         |
         | No Auth::login here. This is the whole point: signing somebody in
         | before they have shown they own the address is what let an account
         | be created against an address its owner never sees.
         */
        event(new Registered($user));

        // Only for rendering the notice, so the guest who has just registered
        // can see which address the link went to.
        $request->session()->put('verification.email', $user->email);

        return redirect()->route('verification.notice')
            ->with('success', 'Almost there. Check your inbox for the link that confirms your email address.');
    }
}
