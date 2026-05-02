<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AnnouncementPublished extends Notification
{
    use Queueable;

    public function __construct(public readonly Announcement $announcement)
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
        $message = (new MailMessage)
            ->subject($this->announcement->title)
            ->greeting('Hi '.($notifiable->first_name ?: $notifiable->name ?: 'there').',');

        if (filled($this->announcement->summary)) {
            $message->line($this->announcement->summary);
        }

        $message->line($this->announcement->body);

        if (filled($this->announcement->action_url)) {
            $message->action(
                $this->announcement->action_label ?: 'Open announcement',
                $this->announcement->action_url,
            );
        } else {
            $message->action('Open dashboard', rtrim(config('app.url'), '/').'/app');
        }

        return $message->salutation('- The WAAIS team');
    }
}
