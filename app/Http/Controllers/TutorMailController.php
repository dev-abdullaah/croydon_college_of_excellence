<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\TutorMail;
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

        try {
            // Send email to recipient
            Mail::to('info@croydoncollegeofexcellence.co.uk')->send(new TutorMail($validatedData));

            // Log success
            Log::info('Tutor Email sent successfully', [
                'recipient' => 'info@croydoncollegeofexcellence.co.uk',
                'data' => $validatedData,
            ]);
            return back()->with('success', 'Your form has been submitted successfully.');

        } catch (Throwable $e) {
            // Log error with more details
            Log::error('Tutor Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('error', 'Failed to submit the form. Please try again.');
        }
    }
}
