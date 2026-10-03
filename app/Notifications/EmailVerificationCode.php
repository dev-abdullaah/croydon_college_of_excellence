<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Proof of an email address, as a code rather than a link.
 *
 * The code is passed in rather than generated here, because generating it and
 * mailing it are two different responsibilities: the model mints it and stores
 * a hash, and this class only carries the plain value to the inbox. Generating
 * it inside the notification would leave nothing able to check it afterwards.
 *
 * This notification must not be queued. A queued job can sit in the queue for
 * minutes, and by the time it is delivered the code it carries may already have
 * been replaced by a newer one - the recipient would then be holding a code the
 * server no longer accepts, and would have no way to tell that from a typo.
 *
 * On this machine MAIL_MAILER is `log`, so the code lands in
 * storage/logs/laravel.log and can be copied out of it. In production the same
 * notification goes out over SMTP. Nothing here knows the difference, which is
 * the point: one code path, two destinations.
 */
class EmailVerificationCode extends Notification
{
    use Queueable;

    /**
     * The plain six digit code. Never stored, never logged by this class.
     */
    public function __construct(
        public readonly string $code,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your verification code - Croydon College of Excellence')
            ->greeting('Confirm your email address')
            // The code is the whole message, so it is given the largest type on
            // the page. Spacing between digits is deliberate: a code read off a
            // phone in daylight should not be mistyped.
            ->line('Enter this code to finish setting up your account:')
            ->line(new HtmlString(
                '<div style="font-size:32px;font-weight:700;letter-spacing:8px;margin:24px 0;">'
                .e($this->code).
                '</div>'
            ))
            ->line('It stops working in '.$this->expiresInMinutes.' minutes.')
            ->line('If you did not create an account, you can ignore this message.')
            ->salutation(new HtmlString('Regards,<br>Croydon College of Excellence'));
    }
}
