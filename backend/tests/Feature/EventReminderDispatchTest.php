<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Event;
use App\Models\EventReminderDelivery;
use App\Models\User;
use App\Notifications\EventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventReminderDispatchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_sends_due_event_reminders_to_approved_verified_members_and_admins(): void
    {
        $this->travelTo('2026-05-02 09:00:00');
        Notification::fake();

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $admin = $this->makeApprovedUser(PermissionRole::Admin);
        $superAdmin = $this->makeApprovedUser(PermissionRole::SuperAdmin);
        $pending = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
            'email_verified_at' => now(),
        ]);
        $unverifiedMember = $this->makeApprovedUser(PermissionRole::Member, ['email_verified_at' => null]);

        $event = $this->makeEvent([
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        Notification::assertSentTo($member, EventReminder::class);
        Notification::assertSentTo($admin, EventReminder::class);
        Notification::assertSentTo($superAdmin, EventReminder::class);
        Notification::assertNotSentTo($pending, EventReminder::class);
        Notification::assertNotSentTo($unverifiedMember, EventReminder::class);

        $this->assertDatabaseHas('event_reminder_deliveries', [
            'event_id' => $event->id,
            'user_id' => $member->id,
            'event_starts_at' => $event->starts_at,
        ]);
        $this->assertSame(3, EventReminderDelivery::where('event_id', $event->id)->count());
    }

    #[Test]
    public function command_only_sends_for_published_non_cancelled_events_due_today(): void
    {
        $this->travelTo('2026-05-02 09:00:00');
        Notification::fake();

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $due = $this->makeEvent([
            'title' => 'Due event',
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);
        $this->makeEvent([
            'title' => 'Too early',
            'starts_at' => now()->addDays(3)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);
        $this->makeEvent([
            'title' => 'Past event',
            'starts_at' => now()->subDay(),
            'reminder_days_before' => 2,
        ]);
        $this->makeEvent([
            'title' => 'Draft event',
            'content_status' => ContentStatus::Draft,
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);
        $this->makeEvent([
            'title' => 'Hidden event',
            'content_status' => ContentStatus::Hidden,
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);
        $this->makeEvent([
            'title' => 'Cancelled event',
            'cancelled_at' => now(),
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        $sent = Notification::sent($member, EventReminder::class);
        $this->assertCount(1, $sent);
        $this->assertSame($due->id, $sent->first()->event->id);
        $this->assertSame(1, EventReminderDelivery::count());
    }

    #[Test]
    public function command_is_idempotent_for_the_same_event_start_time(): void
    {
        $this->travelTo('2026-05-02 09:00:00');
        Notification::fake();

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $event = $this->makeEvent([
            'starts_at' => now()->addDays(2)->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);

        $this->artisan('events:send-reminders')->assertSuccessful();
        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertCount(1, Notification::sent($member, EventReminder::class));
        $this->assertSame(1, EventReminderDelivery::where('event_id', $event->id)->count());
    }

    #[Test]
    public function moved_event_can_receive_a_new_reminder_for_the_new_start_time(): void
    {
        $this->travelTo('2026-05-02 09:00:00');
        Notification::fake();

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $event = $this->makeEvent([
            'starts_at' => now()->addDay()->setTime(18, 0),
            'reminder_days_before' => 2,
        ]);

        EventReminderDelivery::create([
            'event_id' => $event->id,
            'user_id' => $member->id,
            'event_starts_at' => now()->addDay()->setTime(18, 0),
            'sent_at' => now()->subDay(),
        ]);

        $event->update(['starts_at' => now()->addDays(2)->setTime(18, 0)]);

        $this->artisan('events:send-reminders')->assertSuccessful();

        $this->assertCount(1, Notification::sent($member, EventReminder::class));
        $this->assertSame(2, EventReminderDelivery::where('event_id', $event->id)->count());
    }

    #[Test]
    public function event_reminder_mail_uses_registration_url_when_present(): void
    {
        $event = $this->makeEvent([
            'registration_url' => 'https://example.com/register',
            'starts_at' => now()->addDays(2)->setTime(18, 0),
        ]);
        $user = $this->makeApprovedUser(PermissionRole::Member, ['first_name' => 'Avery']);

        $mail = (new EventReminder($event))->toMail($user);

        $this->assertSame('Reminder: AI Founder Salon', $mail->subject);
        $this->assertSame('View registration', $mail->actionText);
        $this->assertSame('https://example.com/register', $mail->actionUrl);
        $this->assertContains('When: '.$event->starts_at->format('M j, Y g:i A T'), $mail->introLines);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeApprovedUser(PermissionRole $role, array $overrides = []): User
    {
        return User::factory()->create(array_replace([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => $role,
            'email_verified_at' => now(),
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(array $overrides = []): Event
    {
        $admin = $this->makeApprovedUser(PermissionRole::Admin, ['email_verified_at' => null]);

        return Event::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::MembersOnly,
            'published_at' => now()->subDay(),
            'title' => 'AI Founder Salon',
            'summary' => 'A focused salon for alumni founders building AI companies.',
            'description' => 'Invitation-only dinner for alumni founders, operators, and investors.',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(3),
            'location' => 'New York',
            'format' => 'Private dinner',
            'registration_url' => 'https://example.com/register',
            'capacity_limit' => 50,
            'reminder_days_before' => 2,
        ], $overrides));
    }
}
