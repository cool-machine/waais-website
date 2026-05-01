<?php

namespace App\Notifications;

use App\Models\MembershipApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipApplicationReceivedByAdmin extends Notification
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
        $applicantName = trim(($this->application->first_name ?? '').' '.($this->application->last_name ?? '')) ?: 'an applicant';
        $email = $this->application->email ?? '';

        return (new MailMessage)
            ->subject('New WAAIS membership application: '.$applicantName)
            ->greeting('A new application is in the queue.')
            ->line('Applicant: '.$applicantName.($email ? ' ('.$email.')' : ''))
            ->line('Affiliation: '.($this->application->affiliation_type?->value ?? 'unspecified'))
            ->action('Open admin queue', rtrim(config('app.url'), '/').'/admin/applications')
            ->salutation('WAAIS admin notice');
    }
}
