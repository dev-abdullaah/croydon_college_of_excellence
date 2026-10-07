<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Support\IntendedCourse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service account creation.
 *
 * An account is what ties a Stripe payment to a person; the webhook matches
 * the payment back to this student through the metadata set at checkout.
 */
class RegisterController extends Controller
{
    public function create(Request $request): View
    {
        return view('website.pages.auth.register', [
            // Shown beside the form so somebody arriving here from a Buy
            // button can see what the account is for before making it. The
            // slug is re-resolved through the database, so this can only ever
            // be a course on this site, and null if there is no such course.
            'intendedCourse' => IntendedCourse::resolve(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /*
         | Deliberately no `unique:students,email` rule.
         |
         | The rule was doing the right thing for the wrong reason: it stopped
         | a second row with the same address, but it also said "that email is
         | taken" to somebody who had simply not finished signing up last time.
         | Abandoning a half-made account is not fraud, and the person coming
         | back to finish it is exactly the person this page should help.
         |
         | So the address is checked for shape only, and what happens next
         | depends on the state of the account it belongs to.
         */
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}\s\-\'\.]+$/u',
                function ($attribute, $value, $fail) {
                    $words = array_filter(explode(' ', trim($value)));
                    if (count($words) === 1 && strlen($words[0]) < 2) {
                        $fail('Please enter at least 2 characters for your name.');
                    }
                },
            ],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'password.confirmed' => 'The two passwords do not match.',
            'name.regex' => 'Name can only contain letters, spaces, hyphens, apostrophes, and periods.',
        ]);

        $email = $validated['email'];

        $existing = Student::where('email', $email)->first();

        if ($existing && $existing->hasVerifiedEmail()) {
            /*
             | A finished account. Say only that the account exists and send
             | them to sign in, rather than anything about its state: the fact
             | that an address is registered is not something a stranger should
             | be able to discover by typing addresses into this form.
             |
             | The intended course is left in the session, so signing in
             | continues straight to it.
             */
            return redirect()->route('login')
                ->with('info', 'You already have an account with this email. Please log in.');
        }

        if ($existing) {
            return $this->resumeRegistration($request, $existing, $validated);
        }

        $student = Student::create([
            'name' => $validated['name'],
            'email' => $email,
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
        $student->sendEmailVerificationCodeNotification();

        // Only for rendering the notice, so the guest who has just registered
        // can see which address the code went to, and enter it without typing
        // the address again.
        $request->session()->put('verification.email', $student->email);
        $request->session()->put('verification.email_locked', true);

        return redirect()->route('verification.notice')
            ->with('success', 'Almost there. Check your inbox for the code that confirms your email address.');
    }

    /**
     * Come back to an account that was created but never verified.
     *
     * This is the "forgot to finish signing up" path. The password is reset to
     * what has just been typed, and a fresh code goes out, so the person ends
     * up in the same place a new customer would be: holding a working
     * password, one code away from being verified.
     *
     * The `email_verified_at` check on entry is the security boundary, and it
     * is not a matter of trusting the caller. Reaching this method with a
     * verified account would let anybody who knows an address overwrite the
     * password on a live account, and take it over. The only accounts that get
     * here are ones that have never proved they own the address, so there is
     * nothing to take over - an address nobody has claimed is not an account
     * anybody can lose.
     */
    private function resumeRegistration(Request $request, Student $student, array $validated): RedirectResponse
    {
        $student->forceFill([
            'name' => $validated['name'],
            'password' => Hash::make($validated['password']),
        ])->save();

        // A code may already be on its way from the abandoned attempt, in
        // which case another email helps nobody. The cooldown message is
        // honest about that: no promise that a code was just sent.
        $sent = $student->sendVerificationCodeIfDue();

        $request->session()->put('verification.email', $student->email);
        $request->session()->put('verification.email_locked', true);

        if (! $sent) {
            return redirect()->route('verification.notice')
                ->with('info', 'A code was sent a moment ago. Please check your inbox and spam folder, or try again in a minute.');
        }

        return redirect()->route('verification.notice')
            ->with('success', 'Almost there. Check your inbox for the code that confirms your email address.');
    }
}
