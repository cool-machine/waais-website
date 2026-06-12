<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyRegistrationEmail extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url)
    {
    }

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
            ->subject('Verify your email to complete your WAAIS application')
            ->greeting('Welcome,')
            ->line('Thanks for applying to the Wharton Alumni AI Studio. Confirm your email address to send your application to our admins for review.')
            ->line('The link expires in 48 hours. If you did not create this account, you can ignore this email.')
            ->action('Verify email & submit application', $this->url)
            ->salutation('— The WAAIS team');
    }
}
