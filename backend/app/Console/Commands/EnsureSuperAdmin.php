<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Bootstrap (and keep) the designated super admin account.
 *
 * Solves the chicken-and-egg problem on a fresh production database:
 * promoting users requires an existing super admin, but the first one
 * has to come from somewhere. The SUPER_ADMIN_EMAIL app setting names
 * that account; once a user registers and verifies with that email,
 * the scheduler promotes them automatically. Idempotent and safe to
 * run every minute.
 */
class EnsureSuperAdmin extends Command
{
    protected $signature = 'waais:ensure-super-admin';

    protected $description = 'Ensure the SUPER_ADMIN_EMAIL account (if registered and verified) holds the super_admin role';

    public function handle(): int
    {
        $email = config('services.super_admin_email');

        if (! is_string($email) || $email === '') {
            return self::SUCCESS;
        }

        $user = User::query()->where('email', mb_strtolower($email))->first();

        if ($user === null || $user->email_verified_at === null) {
            return self::SUCCESS;
        }

        if ($user->permission_role === PermissionRole::SuperAdmin
            && $user->approval_status === ApprovalStatus::Approved) {
            return self::SUCCESS;
        }

        $user->forceFill([
            'permission_role' => PermissionRole::SuperAdmin,
            'approval_status' => ApprovalStatus::Approved,
            'approved_at' => $user->approved_at ?? now(),
        ])->save();

        $this->info("Promoted {$user->email} to super_admin.");

        return self::SUCCESS;
    }
}
