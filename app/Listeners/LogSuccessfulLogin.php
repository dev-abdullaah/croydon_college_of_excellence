<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Jenssegers\Agent\Agent;

class LogSuccessfulLogin
{
    public function __construct(private readonly Request $request) {}

    public function handle(Login $event): void
    {
        $student = $event->user;

        if (! $student->hasVerifiedEmail()) {
            return;
        }

        $agent = new Agent($this->request->header('User-Agent'));

        try {
            LoginHistory::create([
                'student_id'         => $student->id,
                'email'           => $student->email,
                'ip_address'      => $this->request->ip(),
                'device_type'     => $agent->device() ?? 'Desktop',
                'browser'         => $agent->browser(),
                'operating_system'=> $agent->platform(),
                'user_agent'      => $this->request->header('User-Agent'),
                'session_id'      => $this->request->session()->getId(),
                'status'          => 'success',
                'login_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            // Never block authentication because logging failed
            Log::error('Failed to record login history', [
                'student_id' => $student->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}