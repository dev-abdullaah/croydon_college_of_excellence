<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user) {
            return;
        }

        try {
            $loginHistory = LoginHistory::where('user_id', $user->id)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first();

            if ($loginHistory) {
                $loginHistory->update(['logout_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update logout time in login history', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}