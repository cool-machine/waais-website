<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\PermissionRole;
use App\Models\Event;
use App\Models\EventReminderDelivery;
use App\Models\User;
use App\Notifications\EventReminder;
use Illuminate\Console\Command;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send due event reminder emails to approved members, admins, and super admins.';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $windowEnd = now()->addDays(60)->endOfDay();
        $eventsChecked = 0;
        $eventsDue = 0;
        $recipientsChecked = 0;
        $sent = 0;
        $skipped = 0;

        $recipientQuery = User::query()
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereIn('permission_role', [
                PermissionRole::Member,
                PermissionRole::Admin,
                PermissionRole::SuperAdmin,
            ])
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at');

        Event::query()
            ->where('content_status', ContentStatus::Published)
            ->whereNull('cancelled_at')
            ->whereBetween('starts_at', [$today, $windowEnd])
            ->chunkById(100, function ($events) use (
                $recipientQuery,
                $today,
                &$eventsChecked,
                &$eventsDue,
                &$recipientsChecked,
                &$sent,
                &$skipped
            ): void {
                foreach ($events as $event) {
                    $eventsChecked++;

                    $dueDate = $today->copy()->addDays($event->reminder_days_before)->toDateString();
                    if ($event->starts_at->toDateString() !== $dueDate) {
                        continue;
                    }

                    $eventsDue++;

                    (clone $recipientQuery)->chunkById(100, function ($users) use (
                        $event,
                        &$recipientsChecked,
                        &$sent,
                        &$skipped
                    ): void {
                        foreach ($users as $user) {
                            $recipientsChecked++;

                            $delivery = EventReminderDelivery::firstOrCreate([
                                'event_id' => $event->id,
                                'user_id' => $user->id,
                                'event_starts_at' => $event->starts_at,
                            ]);

                            if ($delivery->sent_at !== null) {
                                $skipped++;

                                continue;
                            }

                            $user->notify(new EventReminder($event));

                            $delivery->forceFill(['sent_at' => now()])->save();
                            $sent++;
                        }
                    });
                }
            }, 'id');

        $this->components->info(sprintf(
            'Event reminders complete: %d events checked, %d events due, %d recipients checked, %d sent, %d skipped.',
            $eventsChecked,
            $eventsDue,
            $recipientsChecked,
            $sent,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
