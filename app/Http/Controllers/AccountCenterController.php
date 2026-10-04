<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use App\Models\UserEmail;
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
     * Show the account center (security, password, emails, sessions).
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->load('emails');

        $emails = $user->emails->sortByDesc('is_primary');

        // Ensure the user's primary email (from users table) is included
        // If it's not already in user_emails, add it as the primary
        $primaryEmailFromUser = $user->email;
        $hasPrimaryInUserEmails = $emails->contains('email', $primaryEmailFromUser);

        if (! $hasPrimaryInUserEmails) {
            $emails = $emails->prepend((object)[
                'id' => 0,
                'email' => $primaryEmailFromUser,
                'is_primary' => true,
                'is_verified' => $user->hasVerifiedEmail(),
            ]);
        }

        // Active sessions: not revoked, not logged out, no logout_at
        $activeSessions = LoginHistory::forUser($user->id)
            ->where('status', 'success')
            ->whereNull('logout_at')
            ->latest('login_at')
            ->limit(10)
            ->get();

        // Full login history (including revoked/logged out) for reference
        $loginHistory = LoginHistory::forUser($user->id)
            ->latest('login_at')
            ->limit(10)
            ->get();

        return view('website.pages.account-center', [
            'emails' => $emails,
            'activeSessions' => $activeSessions,
            'loginHistory' => $loginHistory,
        ]);
    }

    /**
     * Add a new email address (requires password).
     */
    public function addEmail(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Rate limit: 5 requests per hour per user
        $key = 'add-email:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => 'Too many requests. Please try again in ' . gmdate('i:s', $seconds) . '.',
            ]);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'email' => ['required', 'email', 'max:255', 'unique:user_emails,email'],
        ]);

        RateLimiter::hit($key, 3600); // 1 hour decay

        // Create the new email record (unverified, not primary)
        $userEmail = UserEmail::create([
            'user_id' => $user->id,
            'email' => $request->string('email')->lower(),
            'is_primary' => false,
            'is_verified' => false,
        ]);

        // Send verification email
        $userEmail->sendVerificationNotification();

        return back()->with('status', 'email-added');
    }

    /**
     * Send verification email for an unverified email.
     */
    public function resendVerification(Request $request, UserEmail $userEmail): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($userEmail->user_id !== $user->id) {
            abort(403);
        }

        if ($userEmail->is_verified) {
            return back()->withErrors(['email' => 'This email is already verified.']);
        }

        // Rate limit: 2 requests per 10 minutes per email
        $key = 'resend-verification:' . $userEmail->id;

        if (RateLimiter::tooManyAttempts($key, 2)) {
            $seconds = RateLimiter::availableIn($key);
            return back()->withErrors([
                'email' => 'Too many verification emails sent. Please wait ' . gmdate('i:s', $seconds) . '.',
            ]);
        }

        RateLimiter::hit($key, 600); // 10 minutes decay

        $userEmail->sendVerificationNotification();

        return back()->with('status', 'verification-sent');
    }

    /**
     * Verify an email address via token from email link.
     */
    public function verifyEmail(Request $request, string $token): RedirectResponse
    {
        $userEmail = UserEmail::where('verification_token', $token)->first();

        if (! $userEmail) {
            return redirect()->route('account.center')
                ->withErrors(['email' => 'Invalid or expired verification link.']);
        }

        if ($userEmail->verifyToken($token)) {
            return redirect()->route('account.center')
                ->with('status', 'email-verified');
        }

        return redirect()->route('account.center')
            ->withErrors(['email' => 'Invalid or expired verification link.']);
    }

    /**
     * Set an email as primary (requires password).
     */
    public function setPrimary(Request $request, UserEmail $userEmail): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($userEmail->user_id !== $user->id) {
            abort(403);
        }

        if (! $userEmail->is_verified) {
            return back()->withErrors(['email' => 'You must verify this email before making it primary.']);
        }

        if ($userEmail->is_primary) {
            return back()->with('status', 'already-primary');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        // Unset current primary
        $user->emails()->where('is_primary', true)->update(['is_primary' => false]);

        // Set new primary
        $userEmail->update(['is_primary' => true]);

        return back()->with('status', 'primary-changed');
    }

    /**
     * Remove an email address (requires password).
     */
    public function removeEmail(Request $request, UserEmail $userEmail): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($userEmail->user_id !== $user->id) {
            abort(403);
        }

        if ($userEmail->is_primary) {
            return back()->withErrors(['email' => 'Cannot remove primary email. Set another email as primary first.']);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $userEmail->delete();

        return back()->with('status', 'email-removed');
    }

    /**
     * Update password (requires current password).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // Prevent reusing current password
        if (Hash::check($request->string('password'), $user->password)) {
            return back()->withErrors([
                'password' => 'The new password must be different from your current password.',
            ])->withInput($request->except('password', 'password_confirmation'));
        }

        $user->password = Hash::make($request->string('password'));
        $user->save();

        return back()->with('status', 'password-changed');
    }

    /**
     * Revoke a specific session (requires password confirmation).
     */
    public function revokeSession(Request $request, LoginHistory $loginHistory): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($loginHistory->user_id !== $user->id) {
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

        return back()->with('status', 'session-revoked');
    }
}