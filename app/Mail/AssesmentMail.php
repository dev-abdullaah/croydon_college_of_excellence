<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssesmentMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->to('info@croydoncollegeofexcellence.co.uk') // Set recipient email here
            ->subject('Free Assesment Request from ' . $this->data['full_name']) // Set email subject
            ->view('emails.assesment_mail_template')
            ->with([
                'full_name' => $this->data['full_name'],
                'dob' => $this->data['dob'],
                'gender' => $this->data['gender'],
                'email' => $this->data['email'],
                'phone' => $this->data['phone'],
                'guardian_name' => $this->data['guardian_name'] ?? 'N/A',
                'guardian_contact' => $this->data['guardian_contact'] ?? 'N/A',
                'address' => $this->data['address'],
                'subjects' => implode(', ', $this->data['subjects'])
            ]);
    }
}
