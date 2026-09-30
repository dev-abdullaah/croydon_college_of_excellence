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
 * Owning proof of an email address, by code instead of by link.
 *
 * The link this replaced was a signed URL: unguessable, single use, and it
 * proved possession by being unopenable to anybody else. A six digit code
 * trades that away - a million values, chosen by a machine, guessable by a
 * script - so what protects the account here is not the code but everything
 * around it:
 *
 *   - the code is stored hashed, so reading the table yields nothing usable;
 *   - it expires in minutes, not hours;
 *   - five wrong answers lock it, and the lockout is timestamped so it cannot
 *     be side-stepped by simply asking again;
 *   - the code is discarded the moment it works, so a forwarded copy is spent;
 *   - the request is throttled per IP on top of all of that;
 *   - and every failure says the same thing, so the form cannot be used to
 *     discover which addresses have accounts.
 *
 * Verification signs the person in. They produced a code that was mailed to the
 * address in question, which is the same proof the link gave, so making them
 * type a password again would prove nothing further and would lose people who
 * did not pick a memorable password. It is a new session id regardless.
 *
 * Shape of the flow, which is the shape Laravel's own password reset uses: one
 * question per page, and a link between the pages rather than a second form
 * stacked underneath. Two email boxes on one screen is the thing this replaces -
 * it reads as two competing forms and nobody knows which one to fill in.
 */
class EmailVerificationController extends Controller
{
    /**
     * Enter the code.
     *
     * Only ever asks for the code. Which address it belongs to is remembered
     * in the session by whoever sent it - registration, or a sign-in attempt
     * against an unverified account - so there is nothing to ask for.
     */
    public function notice(Request $request): View
    {
        $email = $this->pendingEmail($request);

        return view('website.pages.auth.verify-email', [
            // Masked rather than shown in full. This page is routinely open on
            // a shared or public screen, and the person only needs to recognise
            // their own address, not read it out to somebody next to them.
            'email' => $this->mask($email),
            'hasEmail' => $email !== null,
        ]);
    }

    /**
     * Redeem a code.
     *
     * The email is taken from the session rather than the form. It is also
     * accepted from the request as a fallback so that a bookmarked or
     * reloaded form still works; that is not a way in, because the address is
     * not the secret here - the code is. Knowing it only says which account to
     * compare the code against.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ], [
            'code.digits' => 'The code is six digits.',
        ]);

        $email = $this->pendingEmail($request) ?? $request->input('email');

        // Nothing to compare against. The session is gone, so the address has
        // to be asked for again on the resend page.
        if (! $email) {
            return redirect()->route('verification.resend.form');
        }

        $user = User::where('email', $email)->first();

        /*
         | One answer for every failure: no such account, no code issued, no
         | code outstanding, expired, locked out, and wrong. A distinct message
         | for any one of them tells an anonymous visitor which user ids exist
         | and which have simply not finished signing up.
         */
        $invalid = ValidationException::withMessages([
            'code' => 'That code is not right, or it has expired.',
        ]);

        if (! $user instanceof User) {
            throw $invalid;
        }

        // Already verified: their own account, and the only case where the
        // answer can be different, because there is nothing left to prove.
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('dashboard')
                ->with('info', 'Your email address was already verified.');
        }

        if ($user->verificationCodeLocked()) {
            throw $invalid;
        }

        if ($user->verificationCodeExpired() || $user->verification_code_hash === null) {
            throw $invalid;
        }

        if (! $user->verificationCodeMatches($validated['code'])) {
            // Counted against the allowance. Without this the code is only
            // rate limited, and a rate limit is per IP: a botnet walks
            // through a million guesses from a thousand addresses.
            $user->recordFailedVerificationAttempt();

            throw $invalid;
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        // Spent. A code that has been forwarded into a shared inbox is no
        // longer a live credential once it has been redeemed.
        $user->clearVerificationCode();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('verification.email');

        return redirect()->route('dashboard')
            ->with('success', 'Your email address is verified. Welcome to Croydon College of Excellence.');
    }

    /**
     * The page that asks for an address, when a code is needed and we do not
     * know whose.
     *
     * Separate from the code form on purpose. It is the one page that asks for
     * an email address, which is what keeps the code page down to a single
     * question.
     */
    public function resendForm(Request $request): View
    {
        return view('website.pages.auth.resend-code', [
            'email' => $this->mask($this->pendingEmail($request)),
        ]);
    }

    /**
     * Send a code again.
     */
    public function resend(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        /*
         | Deliberately identical wording whether or not an account exists, so
         | this form cannot be used to enumerate who has registered. The one
         | thing that does give the game away - remembering the address that
         | was just asked for - is not evidence of anything on its own, since
         | anybody may type any address in.
         |
         | The throttle on the route is the real defence against using it to
         | send mail, and a locked-out account is skipped as well: otherwise
         | asking again is how the attempt counter gets thrown away.
         */
        if ($user instanceof User && ! $user->hasVerifiedEmail() && ! $user->verificationCodeLocked()) {
            $user->sendEmailVerificationCodeNotification();
        }

        // Remember it, so the code page that follows does not have to ask
        // again. Done for every address, not just one with an account: the
        // code page has to render something either way, and remembering a
        // typed address reveals nothing that typing it did not.
        $request->session()->put('verification.email', $validated['email']);

        return redirect()->route('verification.notice')
            ->with('success', 'If that address needs verifying, a fresh code is on its way.');
    }

    /**
     * Which address a code is outstanding for, if we know it.
     */
    private function pendingEmail(Request $request): ?string
    {
        return $request->user()?->email
            ?? $request->session()->get('verification.email');
    }

    /**
     * Hide all but the first character of the local part.
     *
     * Enough to recognise an address as your own, not enough to read aloud.
     */
    private function mask(?string $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1)
            .str_repeat('•', max(1, min(4, mb_strlen($local) - 1)))
            .'@'.$domain;
    }
}
