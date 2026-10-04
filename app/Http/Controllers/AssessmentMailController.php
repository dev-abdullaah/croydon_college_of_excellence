<?php

namespace App\Http\Controllers;

use App\Mail\AssessmentMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssessmentMailController extends Controller
{
    public function sendMail(Request $request)
    {
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'dob' => 'required|date',
            'gender' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'guardian_name' => 'nullable|string|max:255',
            'guardian_contact' => 'nullable|string|max:20',
            'subjects' => 'required|array|min:1',  // Ensure at least one subject is selected
            'address' => 'required|string|max:255',
            // Add other validation rules as needed
        ]);

        try {

            Mail::to(config('mail.college_inbox'))->send(new AssessmentMail($validatedData));

            // Log success
            Log::info('Free Assessment Email sent successfully', [
                'recipient' => config('mail.college_inbox'),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your free assessment request has been submitted successfully.');

        } catch (Throwable $e) {

            // Log error with more details
            Log::error('Free Assessment Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('error', 'Failed to submit your assessment request. Please try again.');
        }
    }
}
