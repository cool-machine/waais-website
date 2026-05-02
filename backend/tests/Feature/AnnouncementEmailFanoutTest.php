<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Announcement;
use App\Models\AnnouncementEmailDelivery;
use App\Models\User;
use App\Notifications\AnnouncementPublished;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementEmailFanoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function publishing_email_dashboard_announcement_sends_to_approved_verified_members_and_admins(): void
    {
        Notification::fake();

        $actor = $this->makeApprovedUser(PermissionRole::Admin);
        Sanctum::actingAs($actor);

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $admin = $this->makeApprovedUser(PermissionRole::Admin);
        $superAdmin = $this->makeApprovedUser(PermissionRole::SuperAdmin);
        $pending = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
            'email_verified_at' => now(),
        ]);
        $unverifiedMember = $this->makeApprovedUser(PermissionRole::Member, ['email_verified_at' => null]);

        $announcement = $this->makeAnnouncement([
            'created_by' => $actor->id,
            'channel' => 'email_dashboard',
            'audience' => 'all_members',
        ]);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/publish')->assertOk();

        Notification::assertSentTo($member, AnnouncementPublished::class);
        Notification::assertSentTo($admin, AnnouncementPublished::class);
        Notification::assertSentTo($superAdmin, AnnouncementPublished::class);
        Notification::assertSentTo($actor, AnnouncementPublished::class);
        Notification::assertNotSentTo($pending, AnnouncementPublished::class);
        Notification::assertNotSentTo($unverifiedMember, AnnouncementPublished::class);

        $announcement->refresh();
        $this->assertSame(4, AnnouncementEmailDelivery::where('announcement_id', $announcement->id)->count());
        $this->assertDatabaseHas('announcement_email_deliveries', [
            'announcement_id' => $announcement->id,
            'user_id' => $member->id,
            'announcement_published_at' => $announcement->published_at,
        ]);
    }

    #[Test]
    public function admins_audience_emails_only_approved_verified_admins_and_super_admins(): void
    {
        Notification::fake();

        $actor = $this->makeApprovedUser(PermissionRole::Admin);
        Sanctum::actingAs($actor);

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $admin = $this->makeApprovedUser(PermissionRole::Admin);
        $superAdmin = $this->makeApprovedUser(PermissionRole::SuperAdmin);
        $unverifiedAdmin = $this->makeApprovedUser(PermissionRole::Admin, ['email_verified_at' => null]);

        $announcement = $this->makeAnnouncement([
            'created_by' => $actor->id,
            'channel' => 'email_dashboard',
            'audience' => 'admins',
        ]);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/publish')->assertOk();

        Notification::assertNotSentTo($member, AnnouncementPublished::class);
        Notification::assertSentTo($admin, AnnouncementPublished::class);
        Notification::assertSentTo($superAdmin, AnnouncementPublished::class);
        Notification::assertSentTo($actor, AnnouncementPublished::class);
        Notification::assertNotSentTo($unverifiedAdmin, AnnouncementPublished::class);

        $this->assertSame(3, AnnouncementEmailDelivery::where('announcement_id', $announcement->id)->count());
    }

    #[Test]
    public function dashboard_only_announcements_do_not_send_email_on_publish(): void
    {
        Notification::fake();

        $actor = $this->makeApprovedUser(PermissionRole::Admin);
        Sanctum::actingAs($actor);
        $member = $this->makeApprovedUser(PermissionRole::Member);
        $announcement = $this->makeAnnouncement([
            'created_by' => $actor->id,
            'channel' => 'dashboard',
            'audience' => 'all_members',
        ]);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/publish')->assertOk();

        Notification::assertNotSentTo($member, AnnouncementPublished::class);
        Notification::assertNotSentTo($actor, AnnouncementPublished::class);
        $this->assertSame(0, AnnouncementEmailDelivery::count());
    }

    #[Test]
    public function retry_command_sends_missing_deliveries_and_skips_existing_ones(): void
    {
        Notification::fake();
        $this->travelTo('2026-05-02 11:00:00');

        $member = $this->makeApprovedUser(PermissionRole::Member);
        $admin = $this->makeApprovedUser(PermissionRole::Admin);
        $announcement = $this->makeAnnouncement([
            'content_status' => ContentStatus::Published,
            'published_at' => now()->subHour(),
            'channel' => 'email_dashboard',
            'audience' => 'all_members',
        ]);

        AnnouncementEmailDelivery::create([
            'announcement_id' => $announcement->id,
            'user_id' => $member->id,
            'announcement_published_at' => $announcement->published_at,
            'sent_at' => now()->subMinutes(30),
        ]);

        $this->artisan('announcements:send-emails')->assertSuccessful();
        $this->artisan('announcements:send-emails')->assertSuccessful();

        Notification::assertNotSentTo($member, AnnouncementPublished::class);
        $this->assertCount(1, Notification::sent($admin, AnnouncementPublished::class));
        $this->assertSame(2, AnnouncementEmailDelivery::where('announcement_id', $announcement->id)->count());
    }

    #[Test]
    public function retry_command_ignores_unpublished_and_dashboard_only_announcements(): void
    {
        Notification::fake();
        $member = $this->makeApprovedUser(PermissionRole::Member);

        $this->makeAnnouncement([
            'content_status' => ContentStatus::Draft,
            'published_at' => null,
            'channel' => 'email_dashboard',
        ]);
        $this->makeAnnouncement([
            'content_status' => ContentStatus::Hidden,
            'published_at' => now()->subHour(),
            'channel' => 'email_dashboard',
        ]);
        $this->makeAnnouncement([
            'content_status' => ContentStatus::Published,
            'published_at' => now()->subHour(),
            'channel' => 'dashboard',
        ]);

        $this->artisan('announcements:send-emails')->assertSuccessful();

        Notification::assertNotSentTo($member, AnnouncementPublished::class);
        $this->assertSame(0, AnnouncementEmailDelivery::count());
    }

    #[Test]
    public function republished_announcement_can_send_again_for_the_new_published_timestamp(): void
    {
        Notification::fake();
        $this->travelTo('2026-05-02 11:00:00');

        $actor = $this->makeApprovedUser(PermissionRole::Admin);
        Sanctum::actingAs($actor);
        $member = $this->makeApprovedUser(PermissionRole::Member);
        $announcement = $this->makeAnnouncement([
            'created_by' => $actor->id,
            'content_status' => ContentStatus::Hidden,
            'published_at' => now()->subDay(),
            'hidden_at' => now()->subHour(),
            'channel' => 'email_dashboard',
            'audience' => 'all_members',
        ]);

        AnnouncementEmailDelivery::create([
            'announcement_id' => $announcement->id,
            'user_id' => $member->id,
            'announcement_published_at' => $announcement->published_at,
            'sent_at' => now()->subDay(),
        ]);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/publish')->assertOk();

        $this->assertCount(1, Notification::sent($member, AnnouncementPublished::class));
        $this->assertSame(3, AnnouncementEmailDelivery::where('announcement_id', $announcement->id)->count());
    }

    #[Test]
    public function announcement_email_uses_action_url_when_present(): void
    {
        $announcement = $this->makeAnnouncement([
            'action_label' => 'Read more',
            'action_url' => 'https://example.com/announcement',
        ]);
        $user = $this->makeApprovedUser(PermissionRole::Member, ['first_name' => 'Avery']);

        $mail = (new AnnouncementPublished($announcement))->toMail($user);

        $this->assertSame('Forum categories are live', $mail->subject);
        $this->assertSame('Read more', $mail->actionText);
        $this->assertSame('https://example.com/announcement', $mail->actionUrl);
        $this->assertContains('New member discussion spaces are available.', $mail->introLines);
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
    private function makeAnnouncement(array $overrides = []): Announcement
    {
        $admin = $this->makeApprovedUser(PermissionRole::Admin, ['email_verified_at' => null]);

        return Announcement::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Draft,
            'visibility' => ContentVisibility::MembersOnly,
            'audience' => 'all_members',
            'channel' => 'dashboard',
            'title' => 'Forum categories are live',
            'summary' => 'New member discussion spaces are available.',
            'body' => 'We opened new member spaces for founders, operators, research, jobs, and member introductions.',
            'action_label' => 'Open forum',
            'action_url' => 'https://forum.whartonai.studio',
        ], $overrides));
    }
}
