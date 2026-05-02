<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventReminder extends Notification
{
    use Queueable;

    public function __construct(public readonly Event $event)
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
        $startsAt = $this->event->starts_at?->format('M j, Y g:i A T') ?? 'soon';

        $message = (new MailMessage)
            ->subject('Reminder: '.$this->event->title)
            ->greeting('Hi '.($notifiable->first_name ?: $notifiable->name ?: 'there').',')
            ->line('This is a reminder for the upcoming WAAIS event: '.$this->event->title.'.')
            ->line('When: '.$startsAt);

        if (filled($this->event->location)) {
            $message->line('Where: '.$this->event->location);
        }

        if (filled($this->event->summary)) {
            $message->line($this->event->summary);
        }

        if (filled($this->event->registration_url)) {
            $message->action('View registration', $this->event->registration_url);
        } else {
            $message->action('View events', rtrim(config('app.url'), '/').'/events/'.$this->event->id);
        }

        return $message->salutation('- The WAAIS team');
    }
}
