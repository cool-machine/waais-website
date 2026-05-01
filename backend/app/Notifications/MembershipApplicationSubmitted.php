<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationSubmitted extends Notification
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

        return (new MailMessage)
            ->subject('Thanks for applying to the Wharton Alumni AI Studio')
            ->greeting('Hi '.$name.',')
            ->line('Your WAAIS membership application has been received.')
            ->line('A member of the team will review it shortly. We will email you when there is a decision or if we need additional information.')
            ->action('Visit WAAIS', config('app.url'))
            ->salutation('— The WAAIS team');
    }
}
