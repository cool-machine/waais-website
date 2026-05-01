<?php

namespace App\Notifications;

use App\Models\StartupListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StartupListingRejected extends Notification
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
        $message = (new MailMessage)
            ->subject('An update on your WAAIS startup listing')
            ->greeting('Hi '.$name.',')
            ->line('After review, the team is not able to publish your startup listing for "'.$this->listing->name.'" at this time.');

        if (filled($this->listing->review_notes)) {
            $message->line('Reviewer note:')
                ->line($this->listing->review_notes);
        }

        return $message
            ->salutation('— The WAAIS team');
    }
}
