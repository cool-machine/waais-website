<?php

namespace App\Models;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'first_name',
    'last_name',
    'display_name',
    'email',
    'password',
    'google_id',
    'avatar_url',
    'approval_status',
    'affiliation_type',
    'permission_role',
    'approved_at',
    'rejected_at',
    'suspended_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function membershipApplications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class, 'applicant_id');
    }

    public function applicationRevisions(): HasMany
    {
        return $this->hasMany(ApplicationRevision::class, 'actor_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }

    public function isPending(): bool
    {
        return $this->approval_status === ApprovalStatus::Submitted
            && $this->permission_role === PermissionRole::PendingUser;
    }

    public function isMember(): bool
    {
        return $this->canAccessMemberAreas()
            && $this->permission_role === PermissionRole::Member;
    }

    public function isAdmin(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && $this->permission_role?->includesAdminAccess();
    }

    public function isSuperAdmin(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && $this->permission_role === PermissionRole::SuperAdmin;
    }

    public function canAccessMemberAreas(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && $this->permission_role?->includesMemberAccess();
    }

    public function canPublishPublicContent(): bool
    {
        return $this->isAdmin();
    }

    public function canManageAdminPrivileges(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'approval_status' => ApprovalStatus::class,
            'affiliation_type' => AffiliationType::class,
            'permission_role' => PermissionRole::class,
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
