<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $topic,
        public readonly string $message,
    ) {
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
        return (new MailMessage)
            ->subject('[WAAIS contact] '.$this->topic.' — '.$this->name)
            ->replyTo($this->email, $this->name)
            ->greeting('New contact message')
            ->line('From: '.$this->name.' <'.$this->email.'>')
            ->line('Topic: '.$this->topic)
            ->line('Message:')
            ->line($this->message)
            ->salutation('— whartonai.studio contact form');
    }
}
