<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $student = $event->user;

        if (! $student) {
            return;
        }

        try {
            $loginHistory = LoginHistory::where('student_id', $student->id)
                ->whereNull('logout_at')
                ->latest('login_at')
                ->first();

            if ($loginHistory) {
                $loginHistory->update(['logout_at' => now()]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to update logout time in login history', [
                'student_id' => $student->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}