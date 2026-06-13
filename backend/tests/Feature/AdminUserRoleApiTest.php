<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserRoleApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_role_management_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $target = $this->makeMember();

        $this->postJson('/api/admin/users/'.$target->id.'/admin-areas', $this->areas())
            ->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_role_management_routes(): void
    {
        Sanctum::actingAs($this->makeMember());

        $target = $this->makeMember();

        $this->postJson('/api/admin/users/'.$target->id.'/admin-areas', $this->areas())
            ->assertForbidden();
    }

    #[Test]
    public function regular_admin_cannot_access_role_management_routes(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $target = $this->makeMember();

        $this->postJson('/api/admin/users/'.$target->id.'/admin-areas', $this->areas())
            ->assertForbidden();
    }

    #[Test]
    public function super_admin_can_grant_admin_areas_to_member(): void
    {
        $superAdmin = $this->makeSuperAdmin();
        Sanctum::actingAs($superAdmin);

        $target = $this->makeMember();

        $this->postJson('/api/admin/users/'.$target->id.'/admin-areas', $this->areas(['events' => true, 'partners' => true]))
            ->assertOk()
            ->assertJsonPath('data.permission_role', PermissionRole::Admin->value)
            ->assertJsonPath('data.can_manage_events', true)
            ->assertJsonPath('data.can_manage_partners', true)
            ->assertJsonPath('data.can_manage_startups', false);

        $target->refresh();

        $this->assertSame(PermissionRole::Admin, $target->permission_role);
        $this->assertTrue($target->canManageEvents());
        $this->assertTrue($target->canManagePartners());
        $this->assertFalse($target->canManageStartups());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $superAdmin->id,
            'action' => 'role.set_admin_areas',
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
        ]);
    }

    #[Test]
    public function clearing_all_admin_areas_reverts_to_member(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $admin = $this->makeAdmin();

        $this->postJson('/api/admin/users/'.$admin->id.'/admin-areas', $this->areas())
            ->assertOk()
            ->assertJsonPath('data.permission_role', PermissionRole::Member->value);

        $admin->refresh();
        $this->assertSame(PermissionRole::Member, $admin->permission_role);
        $this->assertFalse($admin->canManageEvents());
    }

    #[Test]
    public function set_admin_areas_rejects_super_admin_target(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $other = $this->makeSuperAdmin();

        $this->postJson('/api/admin/users/'.$other->id.'/admin-areas', $this->areas(['events' => true]))
            ->assertStatus(409);
    }

    #[Test]
    public function set_admin_areas_requires_target_to_be_approved(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $unapproved = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::Member,
        ]);

        $this->postJson('/api/admin/users/'.$unapproved->id.'/admin-areas', $this->areas(['events' => true]))
            ->assertStatus(409);
    }

    #[Test]
    public function super_admin_can_promote_admin_to_super_admin(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $admin = $this->makeAdmin();

        $this->postJson('/api/admin/users/'.$admin->id.'/promote-super-admin')
            ->assertOk()
            ->assertJsonPath('data.permission_role', PermissionRole::SuperAdmin->value);

        $admin->refresh();
        $this->assertSame(PermissionRole::SuperAdmin, $admin->permission_role);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'role.promote_super_admin',
            'auditable_id' => $admin->id,
        ]);
    }

    #[Test]
    public function promote_super_admin_rejects_non_admin_target(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $member = $this->makeMember();

        $this->postJson('/api/admin/users/'.$member->id.'/promote-super-admin')
            ->assertStatus(409);
    }

    #[Test]
    public function super_admin_can_demote_another_super_admin(): void
    {
        $actor = $this->makeSuperAdmin();
        $other = $this->makeSuperAdmin();
        Sanctum::actingAs($actor);

        $this->postJson('/api/admin/users/'.$other->id.'/demote-super-admin')
            ->assertOk()
            ->assertJsonPath('data.permission_role', PermissionRole::Admin->value);

        $other->refresh();
        $this->assertSame(PermissionRole::Admin, $other->permission_role);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'role.demote_super_admin',
            'auditable_id' => $other->id,
        ]);
    }

    #[Test]
    public function last_super_admin_cannot_be_demoted(): void
    {
        $actor = $this->makeSuperAdmin();
        Sanctum::actingAs($actor);

        // The actor is the only super admin in the system.
        $this->postJson('/api/admin/users/'.$actor->id.'/demote-super-admin')
            ->assertStatus(409);

        $actor->refresh();
        $this->assertSame(PermissionRole::SuperAdmin, $actor->permission_role);
    }

    #[Test]
    public function last_super_admin_protection_applies_when_demoting_someone_else(): void
    {
        // Two super admins to start: actor + other.
        $actor = $this->makeSuperAdmin();
        $other = $this->makeSuperAdmin();
        Sanctum::actingAs($actor);

        // First demotion of `other` succeeds (actor still left as super admin).
        $this->postJson('/api/admin/users/'.$other->id.'/demote-super-admin')
            ->assertOk();

        // Now actor is the last super admin — self-demotion blocked.
        $this->postJson('/api/admin/users/'.$actor->id.'/demote-super-admin')
            ->assertStatus(409);
    }

    #[Test]
    public function demote_super_admin_rejects_non_super_admin_target(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $admin = $this->makeAdmin();

        $this->postJson('/api/admin/users/'.$admin->id.'/demote-super-admin')
            ->assertStatus(409);
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->eventsAdmin()->create();
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function areas(array $overrides = []): array
    {
        return array_replace(['events' => false, 'partners' => false, 'startups' => false], $overrides);
    }
}
