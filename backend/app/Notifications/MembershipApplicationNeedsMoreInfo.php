<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationNeedsMoreInfo extends Notification
{
    use Queueable;

    public function __construct(public readonly MembershipApplication $application)
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
        $name = trim($this->application->first_name ?? '') ?: 'there';
        $message = (new MailMessage)
            ->subject('We need a bit more information about your WAAIS application')
            ->greeting('Hi '.$name.',')
            ->line('A reviewer needs a little more information before we can finalize your WAAIS membership application.');

        if (filled($this->application->review_notes)) {
            $message->line('Reviewer note:')
                ->line($this->application->review_notes);
        }

        return $message
            ->line('You can update your application at any time.')
            ->action('Update your application', rtrim(config('app.url'), '/').'/membership')
            ->salutation('— The WAAIS team');
    }
}
