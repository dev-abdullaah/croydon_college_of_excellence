<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\ContactMail;
use Throwable;

class ContactMailController extends Controller
{
    public function sendMail(Request $request)
    {
        // Validate the form data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            // Send email to recipient
            Mail::to('info@croydoncollegeofexcellence.co.uk')->send(new ContactMail($validatedData));

            // Log success
            Log::info('Contact Form Email sent successfully', [
                'recipient' => 'info@croydoncollegeofexcellence.co.uk',
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your message has been sent successfully.');
            
        } catch (Throwable $e) {
            // Log error with more details
            Log::error('Contact Form Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('error', 'Failed to send your message. Please try again.');
        }
    }
}
