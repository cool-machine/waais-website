<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactMessageApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_visitor_can_send_a_contact_message(): void
    {
        Notification::fake();

        $this->postJson('/api/contact', [
            'name' => 'Curious Alum',
            'email' => 'alum@example.com',
            'topic' => 'Partnership',
            'message' => 'We would like to sponsor an event.',
        ])->assertCreated()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'alum@example.com',
            'topic' => 'Partnership',
        ]);

        Notification::assertSentOnDemand(
            ContactMessageReceived::class,
            function (ContactMessageReceived $notification, array $channels, AnonymousNotifiable $notifiable): bool {
                return $notifiable->routes['mail'] === config('services.contact.recipient')
                    && $notification->email === 'alum@example.com';
            }
        );
    }

    #[Test]
    public function contact_message_requires_valid_fields(): void
    {
        Notification::fake();

        $this->postJson('/api/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'topic' => 'Nonsense',
            'message' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'topic', 'message']);

        Notification::assertNothingSent();
        $this->assertSame(0, ContactMessage::query()->count());
    }
}
