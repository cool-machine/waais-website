<?php

namespace App\Notifications;

use App\Models\StartupListing;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StartupListingReceivedByAdmin extends Notification
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
        $owner = $this->listing->owner()->first();
        $ownerLine = $owner ? trim(($owner->name ?? '').' ('.($owner->email ?? '').')') : 'an approved member';

        return (new MailMessage)
            ->subject('New WAAIS startup listing: '.$this->listing->name)
            ->greeting('A new startup listing is in the review queue.')
            ->line('Listing: '.$this->listing->name)
            ->line('Industry: '.($this->listing->industry ?? 'unspecified'))
            ->line('Submitted by: '.$ownerLine)
            ->action('Open admin queue', rtrim(config('app.url'), '/').'/admin/startup-listings')
            ->salutation('WAAIS admin notice');
    }
}
