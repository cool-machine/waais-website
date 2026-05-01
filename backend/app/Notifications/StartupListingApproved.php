<?php

namespace App\Notifications;

use App\Models\StartupListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StartupListingApproved extends Notification
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
            ->subject('Your startup listing is live')
            ->greeting('Hi '.$name.',')
            ->line('Your startup listing for "'.$this->listing->name.'" has been approved and is now published in the WAAIS startup directory.');

        if (filled($this->listing->review_notes)) {
            $message->line('Note from the reviewer:')
                ->line($this->listing->review_notes);
        }

        return $message
            ->action('View the directory', rtrim(config('app.url'), '/').'/startups')
            ->salutation('— The WAAIS team');
    }
}
