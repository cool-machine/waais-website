<?php

namespace Tests\Feature;

use App\Console\Commands\SendAnnouncementEmails;
use App\Console\Commands\SendEventReminders;
use App\Models\AnnouncementEmailDelivery;
use App\Models\EventReminderDelivery;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Replaces the manual production smoke check from AZURE_PRODUCTION.md
 * ("publish a test event with a reminder window, publish an
 * email_dashboard announcement, verify the reminder and fan-out emails
 * actually arrive after the scheduler fires") with an automated chain
 * that proves: (1) Artisan exposes both commands, (2) the scheduler is
 * wired to run them at the documented cadence with an overlap guard,
 * (3) the registered scheduled events are due at the documented slot
 * and not at neighbouring ticks, and (4) both commands exit
 * successfully against a freshly migrated empty database. Combined
 * with EventReminderDispatchTest and AnnouncementEmailFanoutTest
 * (which cover audience filtering, idempotency, and republish
 * semantics), this closes the loop without needing a human inbox
 * confirmation in production.
 */
class SchedulerSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The scheduler closure registered in bootstrap/app.php only
        // runs once a schedule command actually executes. Resolving
        // Schedule from the container is not enough — events() comes
        // back empty until the console kernel processes a schedule
        // subcommand. Calling schedule:list once forces the closure to
        // fire so the rest of the assertions can inspect the real
        // schedule.
        $this->app->make(ConsoleKernel::class)->call('schedule:list', [], new \Symfony\Component\Console\Output\NullOutput());
    }

    #[Test]
    public function artisan_exposes_both_scheduled_commands(): void
    {
        $registered = array_keys(Artisan::all());

        $this->assertContains('events:send-reminders', $registered);
        $this->assertContains('announcements:send-emails', $registered);

        $this->assertSame(
            'events:send-reminders',
            (new SendEventReminders())->getName(),
        );
        $this->assertSame(
            'announcements:send-emails',
            (new SendAnnouncementEmails())->getName(),
        );
    }

    #[Test]
    public function schedule_runs_event_reminders_daily_at_09_00_with_overlap_guard(): void
    {
        $event = $this->resolveScheduleEvent('events:send-reminders');

        $this->assertNotNull(
            $event,
            'events:send-reminders is not registered on the schedule.',
        );
        $this->assertSame(
            '0 9 * * *',
            $event->expression,
            'events:send-reminders should run daily at 09:00.',
        );
        $this->assertTrue(
            $event->withoutOverlapping,
            'events:send-reminders should be guarded by withoutOverlapping so a slow run does not stack.',
        );

        // Documented cadence in AZURE_PRODUCTION.md: daily at 09:00.
        $this->travelTo('2026-05-04 09:00:00');
        $this->assertTrue(
            $event->isDue($this->app),
            'events:send-reminders should be due at 09:00 on its scheduled day.',
        );

        $this->travelTo('2026-05-04 09:30:00');
        $this->assertFalse(
            $event->isDue($this->app),
            'events:send-reminders should not fire 30 minutes off the daily slot.',
        );
    }

    #[Test]
    public function schedule_runs_announcement_emails_hourly_with_overlap_guard(): void
    {
        $event = $this->resolveScheduleEvent('announcements:send-emails');

        $this->assertNotNull(
            $event,
            'announcements:send-emails is not registered on the schedule.',
        );
        $this->assertSame(
            '0 * * * *',
            $event->expression,
            'announcements:send-emails should run hourly on the hour.',
        );
        $this->assertTrue(
            $event->withoutOverlapping,
            'announcements:send-emails should be guarded by withoutOverlapping so a slow run does not stack.',
        );

        // Documented cadence in AZURE_PRODUCTION.md: hourly.
        $this->travelTo('2026-05-04 11:00:00');
        $this->assertTrue(
            $event->isDue($this->app),
            'announcements:send-emails should be due at the top of the hour.',
        );

        $this->travelTo('2026-05-04 11:30:00');
        $this->assertFalse(
            $event->isDue($this->app),
            'announcements:send-emails should not fire 30 minutes off the hour.',
        );
    }

    #[Test]
    public function both_commands_succeed_against_an_empty_freshly_migrated_database(): void
    {
        // Mirrors the production smoke-check expectations:
        // "events:send-reminders can run without error" and
        // "announcements:send-emails can run without error" against a
        // database where nothing is currently due. This is the literal
        // dry-run smoke check the App Service WebJob exercises every
        // minute when no event reminder or announcement is queued.
        Notification::fake();

        $this->artisan('events:send-reminders')->assertSuccessful();
        $this->artisan('announcements:send-emails')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, EventReminderDelivery::count());
        $this->assertSame(0, AnnouncementEmailDelivery::count());
    }

    private function resolveScheduleEvent(string $artisanCommand): ?ScheduleEvent
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', $artisanCommand)) {
                return $event;
            }
        }

        return null;
    }
}
