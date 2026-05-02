<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\PermissionRole;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementEmailFanout
{
    /**
     * @return array{recipients_checked: int, sent: int, skipped: int}
     */
    public function send(Announcement $announcement): array
    {
        if (! $this->shouldEmail($announcement)) {
            return ['recipients_checked' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $recipientsChecked = 0;
        $sent = 0;
        $skipped = 0;

        $this->recipientQuery($announcement)
            ->chunkById(100, function ($users) use ($announcement, &$recipientsChecked, &$sent, &$skipped): void {
                foreach ($users as $user) {
                    $recipientsChecked++;

                    $delivery = AnnouncementEmailDelivery::firstOrCreate([
                        'announcement_id' => $announcement->id,
                        'user_id' => $user->id,
                        'announcement_published_at' => $announcement->published_at,
                    ]);

                    if ($delivery->sent_at !== null) {
                        $skipped++;

                        continue;
                    }

                    $user->notify(new AnnouncementPublished($announcement));

                    $delivery->forceFill(['sent_at' => now()])->save();
                    $sent++;
                }
            });

        return [
            'recipients_checked' => $recipientsChecked,
            'sent' => $sent,
            'skipped' => $skipped,
        ];
    }

    public function shouldEmail(Announcement $announcement): bool
    {
        return $announcement->content_status === ContentStatus::Published
            && $announcement->channel === 'email_dashboard'
            && $announcement->published_at !== null;
    }

    private function recipientQuery(Announcement $announcement): Builder
    {
        $query = User::query()
            ->where('approval_status', ApprovalStatus::Approved)
            ->whereNotNull('email')
            ->whereNotNull('email_verified_at');

        if ($announcement->audience === 'admins') {
            return $query->whereIn('permission_role', [
                PermissionRole::Admin,
                PermissionRole::SuperAdmin,
            ]);
        }

        return $query->whereIn('permission_role', [
            PermissionRole::Member,
            PermissionRole::Admin,
            PermissionRole::SuperAdmin,
        ]);
    }
}
