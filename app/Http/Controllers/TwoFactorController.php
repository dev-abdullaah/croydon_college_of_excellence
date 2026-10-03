<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /**
     * Show the 2FA setup page.
     */
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('account.center')
                ->with('status', '2fa-already-enabled');
        }

        // Generate new secret if not already generated in session
        $secretData = session('2fa_setup');
        if (! $secretData) {
            $secretData = $user->enableTwoFactor();
            session(['2fa_setup' => $secretData]);
        }

        return view('website.pages.two-factor-setup', [
            'secret' => $secretData['secret'],
            'qr_code' => $secretData['qr_code_url'],
            'recovery_codes' => $secretData['recovery_codes'],
        ]);
    }

    /**
     * Confirm and enable 2FA.
     */
    public function confirm(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $secretData = session('2fa_setup');
        if (! $secretData) {
            return redirect()->route('account.center')
                ->withErrors(['code' => 'Setup session expired. Please start over.']);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
            'current_password' => ['required', 'current_password'],
        ]);

        if (! $user->confirmTwoFactor($request->string('code'))) {
            return back()->withErrors([
                'code' => 'Invalid code. Please check your authenticator app and try again.',
            ]);
        }

        session()->forget('2fa_setup');

        return redirect()->route('account.center')
            ->with('status', '2fa-enabled');
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => '2FA is not enabled.']);
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
            'current_password' => ['required', 'current_password'],
        ]);

        if (! $user->verifyTwoFactor($request->string('code'))) {
            return back()->withErrors([
                'code' => 'Invalid code. Please check your authenticator app or use a recovery code.',
            ]);
        }

        $user->disableTwoFactor();

        return redirect()->route('account.center')
            ->with('status', '2fa-disabled');
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => '2FA is not enabled.']);
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $codes = $user->regenerateRecoveryCodes();

        return redirect()->route('account.center')
            ->with('recovery_codes', $codes)
            ->with('status', 'recovery-codes-regenerated');
    }
}