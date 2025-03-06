<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->from(env('MAIL_FROM_ADDRESS'))  // Sender address, could be no-reply
            ->to('info@croydoncollegeofexcellence.co.uk')  // Recipient email
            ->subject('Contact Form Message from ' . $this->data['name']) // Email subject
            ->view('emails.contact_mail_template')  // Blade template for email
            ->with('data', $this->data);  // Send form data to view
    }
}
