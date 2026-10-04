<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class EmailChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $newEmail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your email address was changed - Croydon College of Excellence')
            ->greeting('Email address changed')
            ->line('The email address on your account has been changed to <strong>' . e($this->newEmail) . '</strong>.')
            ->line('If you made this change, no further action is needed.')
            ->line('If you <strong>did not</strong> make this change, please contact us immediately at '
                . '<a href="mailto:' . config('mail.college_inbox') . '">' . config('mail.college_inbox') . '</a> '
                . 'or call <a href="tel:+447405073764">+44 7405 073764</a>.')
            ->salutation(new HtmlString('Regards,<br>Croydon College of Excellence'));
    }
}