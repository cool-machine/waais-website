<?php

namespace Tests\Unit;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccessModelTest extends TestCase
{
    #[Test]
    public function pending_users_cannot_access_member_areas(): void
    {
        $user = new User([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $this->assertTrue($user->isPending());
        $this->assertFalse($user->canAccessMemberAreas());
        $this->assertFalse($user->canPublishPublicContent());
    }

    #[Test]
    public function approved_members_can_access_member_areas_but_not_publish_public_content(): void
    {
        $user = new User([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $this->assertTrue($user->isMember());
        $this->assertTrue($user->canAccessMemberAreas());
        $this->assertFalse($user->canPublishPublicContent());
        $this->assertFalse($user->canManageAdminPrivileges());
    }

    #[Test]
    public function admins_can_publish_public_content_but_not_manage_admin_privileges(): void
    {
        $user = new User([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->canPublishPublicContent());
        $this->assertFalse($user->canManageAdminPrivileges());
    }

    #[Test]
    public function only_super_admins_can_manage_admin_privileges(): void
    {
        $user = new User([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->canPublishPublicContent());
        $this->assertTrue($user->canManageAdminPrivileges());
    }

    #[Test]
    public function suspended_users_lose_member_access_even_if_role_remains_member(): void
    {
        $user = new User([
            'approval_status' => ApprovalStatus::Suspended,
            'permission_role' => PermissionRole::Member,
        ]);

        $this->assertFalse($user->canAccessMemberAreas());
        $this->assertFalse($user->canPublishPublicContent());
    }
}
