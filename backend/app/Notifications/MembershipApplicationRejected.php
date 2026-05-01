<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationRejected extends Notification
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
            ->subject('An update on your WAAIS membership application')
            ->greeting('Hi '.$name.',')
            ->line('After reviewing your WAAIS membership application, the team is not able to approve it at this time.');

        if (filled($this->application->review_notes)) {
            $message->line('Reviewer note:')
                ->line($this->application->review_notes);
        }

        return $message
            ->line('You are welcome to reapply in the future.')
            ->salutation('— The WAAIS team');
    }
}
