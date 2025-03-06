<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\EnrollMail;
use Throwable;

class EnrollMailController extends Controller
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

            Mail::to('info@croydoncollegeofexcellence.co.uk')->send(new EnrollMail($validatedData));

            // Log success
            Log::info('Enrollment Email sent successfully', [
                'recipient' => 'info@croydoncollegeofexcellence.co.uk',
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your enrollment request has been submitted successfully.');

        } catch (Throwable $e) {

            // Log error with more details
            Log::error('Enrollment Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('error', 'Failed to submit your enrollment request. Please try again.');
        }
    }
}
