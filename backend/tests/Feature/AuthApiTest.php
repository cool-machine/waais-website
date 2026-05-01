<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function api_user_requires_authentication(): void
    {
        $this->getJson('/api/user')
            ->assertUnauthorized();
    }

    #[Test]
    public function authenticated_users_receive_access_model_flags(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
                'approval_status' => ApprovalStatus::Submitted->value,
                'permission_role' => PermissionRole::PendingUser->value,
                'can_access_member_areas' => false,
                'can_publish_public_content' => false,
                'can_manage_admin_privileges' => false,
            ]);
    }

    #[Test]
    public function pending_users_cannot_access_member_api_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/member/ping')
            ->assertForbidden();
    }

    #[Test]
    public function approved_members_can_access_member_api_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/member/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}
