<?php

namespace App\Notifications;

use App\Models\StartupListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StartupListingNeedsMoreInfo extends Notification
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
            ->subject('We need a bit more information about your startup listing')
            ->greeting('Hi '.$name.',')
            ->line('A reviewer needs a little more information about your startup listing for "'.$this->listing->name.'" before it can be published.');

        if (filled($this->listing->review_notes)) {
            $message->line('Reviewer note:')
                ->line($this->listing->review_notes);
        }

        return $message
            ->line('You can update the listing from your member dashboard.')
            ->action('Update your listing', rtrim(config('app.url'), '/').'/app')
            ->salutation('— The WAAIS team');
    }
}
