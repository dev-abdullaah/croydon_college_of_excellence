<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\Student;
use App\Models\StudentEmail;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountCenterController extends Controller
{
    /**
     * The tabs the account center is divided into, in the order they appear.
     *
     * Which one is open is carried in the URL as `?tab=` rather than in a
     * `#fragment`, because a fragment never reaches the server. That is what
     * made a reload drop the reader back onto Security no matter which tab they
     * were on: the server had never been told, and it rendered the first tab.
     * With the tab in the query string a reload restores it, the address bar
     * names the tab, and the back button steps between tabs.
     */
    private const TABS = ['security', 'emails', 'sessions', 'danger'];

    /**
     * Show the account center (security, password, emails, sessions).
     */
    public function index(Request $request): View
    {
        /** @var Student $student */
        $student = $request->user();
        $student->load('emails');

        $emails = $student->emails->sortByDesc('is_primary');

        // Ensure the student's primary email (from students table) is included
        // If it's not already in student_emails, add it as the primary
        $primaryEmailFromStudent = $student->email;
        $hasPrimaryInStudentEmails = $emails->contains('email', $primaryEmailFromStudent);

        if (! $hasPrimaryInStudentEmails) {
            $emails = $emails->prepend((object)[
                'id' => 0,
                'email' => $primaryEmailFromStudent,
                'is_primary' => true,
                'is_verified' => $student->hasVerifiedEmail(),
            ]);
        }

        // Active sessions: not revoked, not logged out, no logout_at
        $activeSessions = LoginHistory::forStudent($student->id)
            ->where('status', 'success')
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(10)
            ->get();

        // Full login history (including revoked/logged out) with pagination
        $loginHistory = LoginHistory::forStudent($student->id)
            ->latest('login_at')
            ->paginate(10)
            ->withQueryString();

        return view('website.pages.account-center', [
            'emails' => $emails,
            'activeSessions' => $activeSessions,
            'loginHistory' => $loginHistory,
            'activeTab' => $this->normaliseTab($request->query('tab')),
        ]);
    }

    /**
     * Reduce anything that claims to be a tab to one that exists.
     *
     * The list is checked rather than trusted because the value arrives from the
     * query string and then decides which pane is rendered, so an unknown tab
     * must fall back to the first instead of reaching the view.
     */
    private function normaliseTab(mixed $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : self::TABS[0];
    }

    /**
     * Return to the account center on the tab the action came from.
     *
     * Every action here answers with `back()`, which would have sent the reader
     * to the bare `/my-account/security` and so to the Security tab again -
     * deleting an email from the Email Addresses tab appeared to throw them
     * back to the front of the page. The tab is read from the submitted form
     * instead of from the referer, so a browser that withholds the referer for
     * privacy cannot cause the same jump.
     */
    private function backToTab(Request $request): RedirectResponse
    {
        return redirect()->route('account.center', [
            'tab' => $this->normaliseTab($request->input('tab')),
        ]);
    }

    /**
     * Add a new email address (requires password).
     */
    public function addEmail(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        // Rate limit: 5 requests per hour per student
        $key = 'add-email:' . $student->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->backToTab($request)->withErrors([
                'email' => 'Too many requests. Please try again in ' . gmdate('i:s', $seconds) . '.',
            ]);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'email', 'max:255', 'unique:student_emails,email'],
        ]);

        RateLimiter::hit($key, 3600); // 1 hour decay

        // Create the new email record (unverified, not primary)
        $studentEmail = StudentEmail::create([
            'student_id' => $student->id,
            'email' => $request->string('email')->lower(),
            'is_primary' => false,
            'is_verified' => false,
        ]);

        // Send verification email
        $studentEmail->sendVerificationNotification();

        return $this->backToTab($request)->with('status', 'email-added');
    }

    /**
     * Send verification email for an unverified email.
     */
    public function resendVerification(Request $request, StudentEmail $studentEmail): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ($studentEmail->student_id !== $student->id) {
            abort(403);
        }

        if ($studentEmail->is_verified) {
            return $this->backToTab($request)->withErrors(['email' => 'This email is already verified.']);
        }

        // Rate limit: 2 requests per 10 minutes per email
        $key = 'resend-verification:' . $studentEmail->id;

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return $this->backToTab($request)->withErrors([
                'email' => 'Too many verification emails sent. Please wait ' . gmdate('i:s', $seconds) . '.',
            ]);
        }

        RateLimiter::hit($key, 600); // 10 minutes decay

        $studentEmail->sendVerificationNotification();

        return $this->backToTab($request)->with('status', 'verification-sent');
    }

    /**
     * Verify an email address via token from email link.
     */
    public function verifyEmail(Request $request, string $token): RedirectResponse
    {
        // Arriving from the mailed link, so there is no form to carry a tab.
        // The outcome is announced on the Email Addresses tab, which is where
        // the reader is sent whether it worked or not.
        $redirect = redirect()->route('account.center', ['tab' => 'emails']);

        $studentEmail = StudentEmail::where('verification_token', $token)->first();

        if (! $studentEmail) {
            return $redirect->withErrors(['email' => 'Invalid or expired verification link.']);
        }

        if ($studentEmail->verifyToken($token)) {
            return $redirect->with('status', 'email-verified');
        }

        return $redirect->withErrors(['email' => 'Invalid or expired verification link.']);
    }

    /**
     * Set an email as primary (requires password).
     */
    public function setPrimary(Request $request, StudentEmail $studentEmail): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ($studentEmail->student_id !== $student->id) {
            abort(403);
        }

        if (! $studentEmail->is_verified) {
            return $this->backToTab($request)->withErrors(['email' => 'You must verify this email before making it primary.']);
        }

        if ($studentEmail->is_primary) {
            return $this->backToTab($request)->with('status', 'already-primary');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        // Unset current primary
        $student->emails()->where('is_primary', true)->update(['is_primary' => false]);

        // Set new primary
        $studentEmail->update(['is_primary' => true]);

        return $this->backToTab($request)->with('status', 'primary-changed');
    }

    /**
     * Remove an email address (requires password).
     */
    public function removeEmail(Request $request, StudentEmail $studentEmail): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ($studentEmail->student_id !== $student->id) {
            abort(403);
        }

        if ($studentEmail->is_primary) {
            return $this->backToTab($request)->withErrors(['email' => 'Cannot remove primary email. Set another email as primary first.']);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $studentEmail->delete();

        return $this->backToTab($request)->with('status', 'email-removed');
    }

    /**
     * Update password (requires current password).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Prevent reusing current password
        if (Hash::check($request->string('password'), $student->password)) {
            return $this->backToTab($request)->withErrors([
                'password' => 'The new password must be different from your current password.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $student->password = Hash::make($request->string('password'));
        $student->save();

        return $this->backToTab($request)->with('status', 'password-changed');
    }

    /**
     * Revoke a specific session (requires password confirmation).
     */
    public function revokeSession(Request $request, LoginHistory $loginHistory): RedirectResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if ($loginHistory->student_id !== $student->id) {
            abort(403);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        // If this is the current session, redirect to logout
        $currentSessionId = $request->session()->getId();
        if ($loginHistory->session_id === $currentSessionId) {
            return redirect()->route('logout')
                ->with('success', 'Your current session has been revoked. Please sign in again.');
        }

        // For other sessions, delete the session from the sessions table
        if ($loginHistory->session_id) {
            Session::getHandler()->destroy($loginHistory->session_id);
        }

        // Update login history to mark as revoked
        $loginHistory->update([
            'logout_at' => now(),
            'status' => 'revoked',
        ]);

        return $this->backToTab($request)->with('status', 'session-revoked');
    }
}