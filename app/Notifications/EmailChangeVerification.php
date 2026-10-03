<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailChangeVerification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $newEmail,
        public readonly int $expiresInMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url('/my-account/email/verify/' . $this->token);

        return (new MailMessage)
            ->subject('Confirm your new email address - Croydon College of Excellence')
            ->greeting('Confirm your new email address')
            ->line('You requested to change your email address to <strong>' . e($this->newEmail) . '</strong>.')
            ->line('Click the button below to confirm this change:')
            ->action('Confirm Email Change', $url)
            ->line('This link will expire in ' . $this->expiresInMinutes . ' minutes.')
            ->line('If you did not request this change, you can ignore this message — your email address will remain unchanged.')
            ->salutation(new HtmlString('Regards,<br>Croydon College of Excellence'));
    }
}