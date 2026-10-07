<?php

namespace App\Http\Controllers;

use App\Mail\EnrollMail;
use App\Models\ContactSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            'subjects' => 'required|array|min:1',
            'address' => 'required|string|max:255',
        ]);

        // Persist submission to database first so leads are never lost
        try {
            ContactSubmission::create([
                'type' => 'enrollment',
                'name' => $validatedData['full_name'],
                'email' => $validatedData['email'],
                'phone' => $validatedData['phone'],
                'subject' => 'Course Enrollment: '.implode(', ', $validatedData['subjects']),
                'message' => 'Enrollment application from '.$validatedData['full_name'],
                'metadata' => [
                    'dob' => $validatedData['dob'],
                    'gender' => $validatedData['gender'],
                    'guardian_name' => $validatedData['guardian_name'] ?? null,
                    'guardian_contact' => $validatedData['guardian_contact'] ?? null,
                    'subjects' => $validatedData['subjects'],
                    'address' => $validatedData['address'],
                ],
                'status' => 'new',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to persist enrollment submission', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);
        }

        try {
            Mail::to(config('mail.college_inbox'))->send(new EnrollMail($validatedData));

            // Log success
            Log::info('Enrollment Email sent successfully', [
                'recipient' => config('mail.college_inbox'),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your enrollment request has been submitted successfully.');

        } catch (Throwable $e) {
            // Log error with more details
            Log::error('Enrollment Email sending failed', [
                'error' => $e->getMessage(),
                'data' => $validatedData,
            ]);

            return back()->with('success', 'Your enrollment request has been received and our admissions team will contact you shortly.');
        }
    }
}
