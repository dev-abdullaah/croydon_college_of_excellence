<?php

namespace App\Mail;

use App\Models\Admission;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdmissionApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Admission $admission;

    public function __construct(Admission $admission)
    {
        $this->admission = $admission;
    }

    public function build()
    {
        $courseName = $this->admission->course?->name ?? 'Course';

        return $this->from(config('mail.from.address', 'info@croydoncollegeofexcellence.co.uk'), config('mail.from.name', 'Croydon College of Excellence'))
            ->subject("Admission Approved: {$courseName} Access Granted - Croydon College of Excellence")
            ->view('emails.admission_approved')
            ->with([
                'admission' => $this->admission,
                'student'   => $this->admission->student,
                'course'    => $this->admission->course,
            ]);
    }
}
