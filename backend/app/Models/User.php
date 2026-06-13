<?php

namespace App\Models;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

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
    'can_manage_events',
    'can_manage_partners',
    'can_manage_startups',
    'approved_at',
    'rejected_at',
    'suspended_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public function membershipApplications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class, 'applicant_id');
    }

    public function startupListings(): HasMany
    {
        return $this->hasMany(StartupListing::class, 'owner_id');
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

    public function canManageEvents(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && ($this->isSuperAdmin() || (bool) $this->can_manage_events);
    }

    public function canManagePartners(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && ($this->isSuperAdmin() || (bool) $this->can_manage_partners);
    }

    public function canManageStartups(): bool
    {
        return $this->approval_status === ApprovalStatus::Approved
            && ($this->isSuperAdmin() || (bool) $this->can_manage_startups);
    }

    /**
     * Approved users with admin-or-higher permissions. Used as the
     * recipient list for "new submission" admin notifications.
     */
    public function scopeAdmins(Builder $query): void
    {
        $query->where('approval_status', ApprovalStatus::Approved)
            ->whereIn('permission_role', [PermissionRole::Admin, PermissionRole::SuperAdmin]);
    }

    /**
     * Approved super admins only. Recipients for areas that stay
     * super-admin-only (membership approvals, announcements).
     */
    public function scopeSuperAdmins(Builder $query): void
    {
        $query->where('approval_status', ApprovalStatus::Approved)
            ->where('permission_role', PermissionRole::SuperAdmin);
    }

    /**
     * Approved users who can review startup listings: super admins, plus
     * admins scoped to the startups area.
     */
    public function scopeStartupAdmins(Builder $query): void
    {
        $query->where('approval_status', ApprovalStatus::Approved)
            ->where(function (Builder $q): void {
                $q->where('permission_role', PermissionRole::SuperAdmin)
                    ->orWhere('can_manage_startups', true);
            });
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
            'can_manage_events' => 'boolean',
            'can_manage_partners' => 'boolean',
            'can_manage_startups' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }
}
