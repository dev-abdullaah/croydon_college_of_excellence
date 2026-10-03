<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class UserEmailVerification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $email,
        public readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/my-account/emails/verify/' . $this->token);

        return (new MailMessage)
            ->subject('Verify your email address - Croydon College of Excellence')
            ->greeting('Verify your email address')
            ->line('You added <strong>' . e($this->email) . '</strong> to your account.')
            ->line('Click the button below to verify this email address:')
            ->action('Verify Email Address', $url)
            ->line('This link will expire in ' . $this->expiresInMinutes . ' minutes.')
            ->line('If you did not add this email address, you can ignore this message.')
            ->salutation(new HtmlString('Regards,<br>Croydon College of Excellence'));
    }
}