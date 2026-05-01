<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationApproved extends Notification
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
            ->subject('Welcome to the Wharton Alumni AI Studio')
            ->greeting('Hi '.$name.',')
            ->line('Your WAAIS membership application has been approved.')
            ->line('You can now access the member dashboard, the full startup directory, and the member-only forum once it goes live.');

        if (filled($this->application->review_notes)) {
            $message->line('Note from the team:')
                ->line($this->application->review_notes);
        }

        return $message
            ->action('Sign in to your dashboard', rtrim(config('app.url'), '/').'/app')
            ->salutation('— The WAAIS team');
    }
}
