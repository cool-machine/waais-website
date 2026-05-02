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

class AdminAnnouncementApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function regular_member_cannot_access_admin_announcement_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/announcements')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_a_draft_announcement(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/announcements', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Forum categories are live')
            ->assertJsonPath('data.content_status', ContentStatus::Draft->value)
            ->assertJsonPath('data.visibility', ContentVisibility::MembersOnly->value)
            ->assertJsonPath('data.audience', 'all_members');

        $announcement = Announcement::find($response->json('data.id'));
        $this->assertSame($admin->id, $announcement->created_by);
        $this->assertNull($announcement->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'announcements.create',
            'auditable_type' => Announcement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    #[Test]
    public function announcement_creation_validates_required_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/announcements', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'body']);
    }

    #[Test]
    public function admin_can_update_an_announcement_and_changes_are_audited(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $announcement = $this->makeAnnouncement(['created_by' => $admin->id]);

        $this->patchJson('/api/admin/announcements/'.$announcement->id, [
            'title' => 'Forum spaces are live',
            'audience' => 'admins',
        ])->assertOk()->assertJsonPath('data.title', 'Forum spaces are live');

        $announcement->refresh();
        $this->assertSame('Forum spaces are live', $announcement->title);
        $this->assertSame('admins', $announcement->audience);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'announcements.update',
            'auditable_type' => Announcement::class,
            'auditable_id' => $announcement->id,
        ]);
    }

    #[Test]
    public function admin_can_publish_hide_and_archive_announcement(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $announcement = $this->makeAnnouncement();

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/publish')
            ->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);

        $announcement->refresh();
        $this->assertSame(ContentStatus::Published, $announcement->content_status);
        $this->assertNotNull($announcement->published_at);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/hide')
            ->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Hidden->value);
        $this->assertNotNull($announcement->refresh()->hidden_at);

        $this->postJson('/api/admin/announcements/'.$announcement->id.'/archive')
            ->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Archived->value);
        $this->assertNotNull($announcement->refresh()->archived_at);
    }

    #[Test]
    public function index_can_filter_by_content_status_and_audience(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $memberDraft = $this->makeAnnouncement(['audience' => 'all_members']);
        $this->makeAnnouncement(['audience' => 'admins']);
        $published = $this->makeAnnouncement([
            'content_status' => ContentStatus::Published,
            'published_at' => now(),
            'audience' => 'all_members',
        ]);

        $draftIds = collect($this->getJson('/api/admin/announcements?content_status=draft')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$memberDraft->id], array_values(array_intersect($draftIds, [$memberDraft->id])));
        $this->assertNotContains($published->id, $draftIds);

        $memberIds = collect($this->getJson('/api/admin/announcements?audience=all_members')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($memberDraft->id, $memberIds);
        $this->assertContains($published->id, $memberIds);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeAnnouncement(array $overrides = []): Announcement
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Forum categories are live',
            'summary' => 'New member discussion spaces are available.',
            'body' => 'We opened new member spaces for founders, operators, research, jobs, and member introductions.',
            'visibility' => ContentVisibility::MembersOnly->value,
            'audience' => 'all_members',
            'channel' => 'dashboard',
            'action_label' => 'Open forum',
            'action_url' => 'https://forum.whartonai.studio',
        ], $overrides);
    }
}
