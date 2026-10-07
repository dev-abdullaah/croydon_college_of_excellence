<?php

namespace App\Http\Controllers;

use App\Mail\ContactMail;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

        // Persist submission to database first so leads are never lost
        try {
            ContactSubmission::create([
                'type' => 'contact',
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'subject' => $validatedData['subject'],
                'message' => $validatedData['message'],
                'status' => 'new',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to persist contact submission', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);
        }

        try {
            // Send email to recipient
            Mail::to(config('mail.college_inbox'))->send(new ContactMail($validatedData));

            // Log success
            Log::info('Contact Form Email sent successfully', [
                'recipient' => config('mail.college_inbox'),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your message has been sent successfully.');

        } catch (Throwable $e) {
            // Log error with more details
            Log::error('Contact Form Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your message has been received and our team will get in touch shortly.');
        }
    }
}
