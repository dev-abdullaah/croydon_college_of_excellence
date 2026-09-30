<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
         | The code is sent here rather than by firing Registered and hoping a
         | listener picks it up. Laravel's own listener would send its link
         | notification, which is the mechanism this replaced, so sending the
         | code explicitly is what guarantees the thing that actually gets
         | delivered is the code.
         |
         | No Auth::login here. This is the whole point: signing somebody in
         | before they have shown they own the address is what let an account
         | be created against an address its owner never sees.
         */
        // Minted and mailed here. See the note above: firing Registered instead
        // would send Laravel's link notification, not this code.
        $user->sendEmailVerificationCodeNotification();

        // Only for rendering the notice, so the guest who has just registered
        // can see which address the code went to, and enter it without typing
        // the address again.
        $request->session()->put('verification.email', $user->email);
        $request->session()->put('verification.email_locked', true);

        return redirect()->route('verification.notice')
            ->with('success', 'Almost there. Check your inbox for the code that confirms your email address.');
    }
}
