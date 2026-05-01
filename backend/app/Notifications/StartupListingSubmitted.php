<?php

namespace App\Notifications;

use App\Models\StartupListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StartupListingSubmitted extends Notification
{
    use Queueable;

    public function __construct(public readonly StartupListing $listing)
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
        $name = trim($notifiable->name ?? '') ?: 'there';

        return (new MailMessage)
            ->subject('Your startup listing was received')
            ->greeting('Hi '.$name.',')
            ->line('Your startup listing for "'.$this->listing->name.'" has been submitted to the WAAIS directory.')
            ->line('A reviewer will look at it shortly. We will email you when it is approved or if more information is needed.')
            ->action('Visit WAAIS', config('app.url'))
            ->salutation('— The WAAIS team');
    }
}
