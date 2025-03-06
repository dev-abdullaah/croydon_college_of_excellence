<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TutorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS')) // Sender address, could be no-reply
            ->to('info@croydoncollegeofexcellence.co.uk') // Your recipient email
            ->subject('Tutor Registration Request from ' . $this->data['full_name']) // Subject for the email
            ->view('emails.tutor_mail_template') // The email template to use
            ->with('data', $this->data); // Data sent to the view
    }
}
