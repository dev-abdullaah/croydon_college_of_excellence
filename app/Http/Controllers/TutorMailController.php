<?php

namespace App\Http\Controllers;

use App\Mail\TutorMail;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TutorMailController extends Controller
{
    public function sendMail(Request $request)
    {
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'qualification' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'comments' => 'nullable|string',
        ]);

        // Persist submission to database first so leads are never lost
        try {
            ContactSubmission::create([
                'type' => 'tutor',
                'name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'subject' => 'Tutor Application: '.$validatedData['subject'],
                'message' => $validatedData['comments'] ?? 'Tutor application submitted.',
                'metadata' => [
                    'qualification' => $validatedData['qualification'],
                    'subject' => $validatedData['subject'],
                ],
                'status' => 'new',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to persist tutor application submission', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);
        }

        try {
            // Send email to recipient
            Mail::to(config('mail.college_inbox'))->send(new TutorMail($validatedData));

            // Log success
            Log::info('Tutor Email sent successfully', [
                'recipient' => config('mail.college_inbox'),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your form has been submitted successfully.');

        } catch (Throwable $e) {
            // Log error with more details
            Log::error('Tutor Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your application has been received and our academic coordinator will review it.');
        }
    }
}
