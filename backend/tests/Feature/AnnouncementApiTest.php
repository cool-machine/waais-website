<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_cannot_read_announcements(): void
    {
        $this->getJson('/api/announcements')->assertUnauthorized();
    }

    #[Test]
    public function pending_user_cannot_read_announcements(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/announcements')->assertForbidden();
    }

    #[Test]
    public function member_index_returns_only_published_member_visible_announcements(): void
    {
        Sanctum::actingAs($this->makeMember());

        $visible = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::MembersOnly, ['title' => 'Member Update']);
        $mixed = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::Mixed, ['title' => 'Mixed Update']);
        $public = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::Public, ['title' => 'Public Update']);
        $this->makeAnnouncement(ContentStatus::Draft, ContentVisibility::MembersOnly, ['title' => 'Draft']);
        $this->makeAnnouncement(ContentStatus::Hidden, ContentVisibility::MembersOnly, ['title' => 'Hidden']);
        $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::MembersOnly, [
            'title' => 'Admin Only',
            'audience' => 'admins',
        ]);

        $response = $this->getJson('/api/announcements')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->sort()->values()->all();
        $this->assertSame(['Member Update', 'Mixed Update', 'Public Update'], $titles);
        $this->assertContains($visible->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertContains($mixed->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertContains($public->id, collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function admin_read_includes_admin_audience_announcements(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]));

        $adminOnly = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::MembersOnly, [
            'title' => 'Admin Only',
            'audience' => 'admins',
        ]);

        $ids = collect($this->getJson('/api/announcements')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($adminOnly->id, $ids);
    }

    #[Test]
    public function show_returns_published_visible_announcement(): void
    {
        Sanctum::actingAs($this->makeMember());
        $announcement = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::MembersOnly);

        $this->getJson('/api/announcements/'.$announcement->id)
            ->assertOk()
            ->assertJsonPath('data.id', $announcement->id)
            ->assertJsonPath('data.title', 'Forum categories are live');
    }

    #[Test]
    public function show_returns_404_for_unpublished_announcement(): void
    {
        Sanctum::actingAs($this->makeMember());
        $draft = $this->makeAnnouncement(ContentStatus::Draft, ContentVisibility::MembersOnly);

        $this->getJson('/api/announcements/'.$draft->id)->assertNotFound();
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        Sanctum::actingAs($this->makeMember());
        $announcement = $this->makeAnnouncement(ContentStatus::Published, ContentVisibility::MembersOnly);

        $payload = $this->getJson('/api/announcements/'.$announcement->id)->assertOk()->json('data');

        $expected = [
            'id', 'title', 'summary', 'body', 'visibility', 'audience',
            'channel', 'action_label', 'action_url', 'published_at',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing announcement field: {$field}");
        }

        $actualKeys = array_keys($payload);
        sort($actualKeys);
        $expectedKeys = $expected;
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Member announcement projection key set drifted from the documented allowlist.');

        $forbidden = [
            'created_by', 'creator', 'content_status', 'hidden_at',
            'archived_at', 'created_at', 'updated_at',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $payload, "Member announcement projection leaked: {$field}");
        }
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAnnouncement(ContentStatus $contentStatus, ContentVisibility $visibility, array $overrides = []): Announcement
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return Announcement::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => $contentStatus,
            'visibility' => $visibility,
            'published_at' => $contentStatus === ContentStatus::Published ? now() : null,
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
