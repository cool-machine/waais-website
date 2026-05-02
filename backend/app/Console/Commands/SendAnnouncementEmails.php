<?php

namespace App\Console\Commands;

use App\Enums\ContentStatus;
use App\Models\Announcement;
use App\Services\AnnouncementEmailFanout;
use Illuminate\Console\Command;

class SendAnnouncementEmails extends Command
{
    protected $signature = 'announcements:send-emails';

    protected $description = 'Send or retry email fan-out for published email-dashboard announcements.';

    public function handle(AnnouncementEmailFanout $fanout): int
    {
        $announcementsChecked = 0;
        $recipientsChecked = 0;
        $sent = 0;
        $skipped = 0;

        Announcement::query()
            ->where('content_status', ContentStatus::Published)
            ->where('channel', 'email_dashboard')
            ->whereNotNull('published_at')
            ->chunkById(100, function ($announcements) use (
                $fanout,
                &$announcementsChecked,
                &$recipientsChecked,
                &$sent,
                &$skipped
            ): void {
                foreach ($announcements as $announcement) {
                    $announcementsChecked++;
                    $result = $fanout->send($announcement);

                    $recipientsChecked += $result['recipients_checked'];
                    $sent += $result['sent'];
                    $skipped += $result['skipped'];
                }
            });

        $this->components->info(sprintf(
            'Announcement emails complete: %d announcements checked, %d recipients checked, %d sent, %d skipped.',
            $announcementsChecked,
            $recipientsChecked,
            $sent,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
