<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeamMemberApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function regular_member_cannot_access_admin_team_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/team-members')->assertForbidden();
    }

    #[Test]
    public function super_admin_can_create_and_publish_a_team_member(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/team-members', [
            'name' => 'George Gvishiani',
            'role_title' => 'Founder & Chairman',
            'member_group' => 'founder',
            'bio' => 'Founder of the Wharton Alumni AI Studio.',
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'photo_url' => '/team/george.jpg',
            'visibility' => 'public',
        ])->assertCreated()
            ->assertJsonPath('data.member_group', 'founder')
            ->assertJsonPath('data.content_status', ContentStatus::Draft->value);

        $id = $created->json('data.id');

        // Draft is not visible publicly yet.
        $this->getJson('/api/public/team-members')->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/admin/team-members/{$id}/publish")
            ->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);

        $this->getJson('/api/public/team-members')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'George Gvishiani')
            ->assertJsonPath('data.0.role_title', 'Founder & Chairman');
    }

    #[Test]
    public function public_endpoint_lists_founders_before_advisors(): void
    {
        $admin = User::factory()->superAdmin()->create();

        TeamMember::create([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
            'published_at' => now(),
            'name' => 'Advisor One',
            'role_title' => 'Board Advisor',
            'member_group' => 'advisor',
            'sort_order' => 0,
        ]);
        TeamMember::create([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
            'published_at' => now(),
            'name' => 'Founder One',
            'role_title' => 'Founder & Chairman',
            'member_group' => 'founder',
            'sort_order' => 0,
        ]);

        $data = $this->getJson('/api/public/team-members')->assertOk()->json('data');

        $this->assertSame('Founder One', $data[0]['name']);
        $this->assertSame('Advisor One', $data[1]['name']);
    }
}
