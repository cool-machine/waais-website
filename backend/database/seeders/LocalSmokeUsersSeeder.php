<?php

namespace Database\Seeders;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local smoke seeder. Idempotent. Creates / updates a small fixture set of
 * users so the admin user directory at /app/users has variety to render
 * during browser smokes. Not part of the production seeder chain.
 *
 * Run with:  php artisan db:seed --class=LocalSmokeUsersSeeder --force
 */
class LocalSmokeUsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(['email' => 'cool.lstm@gmail.com'], [
            'name' => 'George Chen',
            'first_name' => 'George',
            'last_name' => 'Chen',
            'approval_status' => ApprovalStatus::Approved,
            'affiliation_type' => AffiliationType::Alumni,
            'permission_role' => PermissionRole::SuperAdmin,
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'ada.lovelace@example.com'], [
            'name' => 'Ada Lovelace',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'approval_status' => ApprovalStatus::Approved,
            'affiliation_type' => AffiliationType::Alumni,
            'permission_role' => PermissionRole::Admin,
            'approved_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'grace.hopper@example.com'], [
            'name' => 'Grace Hopper',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'approval_status' => ApprovalStatus::Approved,
            'affiliation_type' => AffiliationType::Alumni,
            'permission_role' => PermissionRole::Member,
            'approved_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'alan.turing@example.com'], [
            'name' => 'Alan Turing',
            'first_name' => 'Alan',
            'last_name' => 'Turing',
            'approval_status' => ApprovalStatus::Approved,
            'affiliation_type' => AffiliationType::FacultyStaff,
            'permission_role' => PermissionRole::Member,
            'approved_at' => now(),
        ]);

        User::updateOrCreate(['email' => 'katherine.johnson@example.com'], [
            'name' => 'Katherine Johnson',
            'first_name' => 'Katherine',
            'last_name' => 'Johnson',
            'approval_status' => ApprovalStatus::Submitted,
            'affiliation_type' => AffiliationType::Student,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        User::updateOrCreate(['email' => 'don.knuth@example.com'], [
            'name' => 'Donald Knuth',
            'first_name' => 'Donald',
            'last_name' => 'Knuth',
            'approval_status' => ApprovalStatus::Rejected,
            'affiliation_type' => AffiliationType::Other,
            'permission_role' => PermissionRole::PendingUser,
            'rejected_at' => now(),
        ]);

        $this->command?->info('LocalSmokeUsersSeeder: '.User::count().' users in DB.');
    }
}
