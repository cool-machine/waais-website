<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailSignInLink extends Notification
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
            ->subject('Your WAAIS sign-in link')
            ->greeting('Hi there,')
            ->line('Use this secure link to start or resume your WAAIS membership application.')
            ->line('The link expires in 30 minutes.')
            ->action('Open membership application', $this->url)
            ->salutation('— The WAAIS team');
    }
}
