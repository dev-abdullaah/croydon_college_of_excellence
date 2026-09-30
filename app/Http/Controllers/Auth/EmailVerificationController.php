<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Owning proof of an email address.
 *
 * The verify link is a temporary signed URL, so it cannot be guessed or
 * edited, and it carries a hash of the address it was issued for. That second
 * part is what makes changing an email safe: if somebody verifies, then edits
 * their address, every link already sitting in their inbox stops working,
 * because the hash no longer matches the address they now hold.
 *
 * Verification signs the person in. They arrived from a link that was mailed
 * to the address in question and nobody else could have produced it, so
 * making them type a password again would prove nothing that the link has not
 * already proved, and would only lose people who did not pick a password they
 * remember. It is a new session id either way, so this is not a fixation risk.
 */
class EmailVerificationController extends Controller
{
    /**
     * Where the person goes once their address is proven.
     */
    public function notice(Request $request): View
    {
        return view('website.pages.auth.verify-email', [
            'email' => $request->user()?->email
                ?? $request->session()->get('verification.email'),
        ]);
    }

    /**
     * Consume the link from the email.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        // One message for "no such account", "wrong hash" and "address changed
        // since the link was sent". Distinguishing them would tell an
        // anonymous visitor which user ids exist.
        $mismatch = ! $user instanceof User
            || ! hash_equals(sha1($user->getEmailForVerification()), $hash);

        if ($mismatch) {
            throw ValidationException::withMessages([
                'email' => 'This verification link is no longer valid. Please request a new one.',
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard')
                ->with('info', 'Your email address was already verified.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        // Signing somebody in because they followed a mailed link is only
        // acceptable because that link is unforgeable. Regenerating the
        // session keeps the usual protections in place.
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('verification.email');

        return redirect()->route('dashboard')
            ->with('success', 'Your email address is verified. Welcome to Croydon College of Excellence.');
    }

    /**
     * Send the link again.
     */
    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Deliberately identical wording whether or not an account exists, so
        // this form cannot be used to enumerate who has registered. The
        // throttle is the real defence against using it to send mail.
        if ($user instanceof User && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return redirect()->route('verification.notice')
            ->with('success', 'If that address needs verifying, a fresh link is on its way.');
    }
}
