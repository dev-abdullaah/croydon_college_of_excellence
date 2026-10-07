<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InquiryAutoresponderMail extends Mailable
{
    use Queueable, SerializesModels;

    public array $data;
    public string $formType;

    public function __construct(array $data, string $formType = 'inquiry')
    {
        $this->data = $data;
        $this->formType = $formType;
    }

    public function build()
    {
        $subject = match ($this->formType) {
            'enrollment' => 'Thank you for your enrollment inquiry - Croydon College of Excellence',
            'assessment' => 'Your free assessment request has been received - Croydon College of Excellence',
            'tutor'      => 'Thank you for your tutor application - Croydon College of Excellence',
            default      => 'We received your inquiry - Croydon College of Excellence',
        };

        return $this->from(config('mail.from.address', 'info@croydoncollege.co.uk'), config('mail.from.name', 'Croydon College of Excellence'))
            ->subject($subject)
            ->view('emails.inquiry_autoresponder')
            ->with([
                'data'     => $this->data,
                'formType' => $this->formType,
            ]);
    }
}
