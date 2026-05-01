<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only seeder used for the public-startups-frontend smoke check.
 * Inserts two approved+published+public listings plus one members_only
 * (must be invisible to the public API) plus one pending_review (also
 * invisible). Not part of the production seeder chain.
 */
class SmokeStartupSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        StartupListing::create([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
            'submitted_at' => now()->subDays(2),
            'reviewed_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
            'name' => 'AutoFlow AI',
            'tagline' => 'Workflow automation for B2B teams.',
            'description' => 'AutoFlow AI helps operations teams orchestrate end-to-end workflows with LLMs in the loop. Founded by Wharton alumni in 2024.',
            'website_url' => 'https://autoflow.example.com',
            'industry' => 'AI Engineering',
            'stage' => 'Seed',
            'location' => 'New York, NY',
            'founders' => ['Daniel Reed', 'Priya Patel'],
            'submitter_role' => 'Cofounder & CEO',
            'linkedin_url' => 'https://www.linkedin.com/company/autoflow',
        ]);

        StartupListing::create([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subHours(6),
            'approved_at' => now()->subHours(6),
            'name' => 'EcoPredict',
            'tagline' => 'Predictive analytics for sustainability decisions.',
            'description' => 'EcoPredict turns environmental telemetry into operational decisions for climate-impact companies.',
            'website_url' => 'https://ecopredict.example.com',
            'industry' => 'Climate Tech',
            'stage' => 'Pre-seed',
            'location' => 'San Francisco, CA',
            'founders' => ['Aiden Park'],
            'submitter_role' => 'Founder',
        ]);

        StartupListing::create([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::MembersOnly,
            'submitted_at' => now()->subDays(3),
            'reviewed_at' => now()->subDays(2),
            'approved_at' => now()->subDays(2),
            'name' => 'Hidden Members-Only Co',
            'tagline' => 'Should not appear in the public directory.',
            'description' => 'This listing is members-only and should be invisible to anonymous callers.',
            'industry' => 'Stealth',
        ]);

        StartupListing::create([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Submitted,
            'content_status' => ContentStatus::PendingReview,
            'visibility' => ContentVisibility::Public,
            'submitted_at' => now()->subHours(2),
            'name' => 'Pending Review Co',
            'tagline' => 'Should not appear in the public directory yet.',
            'description' => 'This listing is awaiting admin review.',
            'industry' => 'AI Engineering',
        ]);
    }
}
