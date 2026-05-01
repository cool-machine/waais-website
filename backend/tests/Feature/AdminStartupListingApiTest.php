<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\AuditLog;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminStartupListingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_admin_startup_listing_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/startup-listings')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_admin_startup_listing_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/startup-listings')->assertForbidden();
    }

    #[Test]
    public function admin_can_list_startup_listing_queue_filtered_by_status(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $submitted = $this->makeListing(ApprovalStatus::Submitted);
        $this->makeListing(ApprovalStatus::Approved);
        $this->makeListing(ApprovalStatus::Rejected);

        $response = $this->getJson('/api/admin/startup-listings?status=submitted')
            ->assertOk()
            ->assertJsonPath('data.0.id', $submitted->id);

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function admin_can_view_a_single_startup_listing_with_revisions_and_reviewer(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $listing = $this->makeListing(ApprovalStatus::Submitted);

        $this->getJson('/api/admin/startup-listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.owner.id', $listing->owner_id);
    }

    #[Test]
    public function admin_can_approve_a_listing_and_publish_it(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $listing = $this->makeListing(ApprovalStatus::Submitted);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/approve', [
            'review_notes' => 'Looks great, publishing.',
        ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', ApprovalStatus::Approved->value)
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);

        $listing->refresh();

        $this->assertSame(ApprovalStatus::Approved, $listing->approval_status);
        $this->assertSame(ContentStatus::Published, $listing->content_status);
        $this->assertSame($admin->id, $listing->reviewed_by);
        $this->assertNotNull($listing->reviewed_at);
        $this->assertNotNull($listing->approved_at);
        $this->assertSame('Looks great, publishing.', $listing->review_notes);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'startup_listings.approve',
            'auditable_type' => StartupListing::class,
            'auditable_id' => $listing->id,
        ]);
    }

    #[Test]
    public function admin_can_reject_a_listing_and_review_notes_are_required(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $listing = $this->makeListing(ApprovalStatus::Submitted);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_notes']);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/reject', [
            'review_notes' => 'Not aligned with directory criteria.',
        ])->assertOk();

        $listing->refresh();

        $this->assertSame(ApprovalStatus::Rejected, $listing->approval_status);
        $this->assertSame(ContentStatus::Hidden, $listing->content_status);
        $this->assertNotNull($listing->rejected_at);
        $this->assertNull($listing->approved_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'startup_listings.reject',
        ]);
    }

    #[Test]
    public function admin_can_request_more_info_and_review_notes_are_required(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $listing = $this->makeListing(ApprovalStatus::Submitted);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/request-info', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_notes']);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/request-info', [
            'review_notes' => 'Please add a website URL.',
        ])->assertOk();

        $listing->refresh();

        $this->assertSame(ApprovalStatus::NeedsMoreInfo, $listing->approval_status);
        $this->assertSame(ContentStatus::Draft, $listing->content_status);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->rejected_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'startup_listings.request_info',
        ]);
    }

    #[Test]
    public function super_admin_can_use_admin_startup_listing_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]));

        $this->getJson('/api/admin/startup-listings')->assertOk();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);
    }

    private function makeListing(ApprovalStatus $status): StartupListing
    {
        $owner = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $contentStatus = match ($status) {
            ApprovalStatus::Approved => ContentStatus::Published,
            ApprovalStatus::Rejected => ContentStatus::Hidden,
            ApprovalStatus::NeedsMoreInfo => ContentStatus::Draft,
            default => ContentStatus::PendingReview,
        };

        return StartupListing::create([
            'owner_id' => $owner->id,
            'approval_status' => $status,
            'content_status' => $contentStatus,
            'visibility' => ContentVisibility::Public,
            'submitted_at' => now(),
            'name' => 'AutoFlow AI',
            'tagline' => 'Workflow automation for B2B teams.',
            'description' => 'AutoFlow AI helps ops teams orchestrate workflows with LLMs.',
            'website_url' => 'https://autoflow.example.com',
            'industry' => 'AI Engineering',
            'stage' => 'Seed',
            'location' => 'New York, NY',
            'founders' => ['Daniel Reed'],
            'submitter_role' => 'Cofounder & CEO',
            'linkedin_url' => 'https://www.linkedin.com/company/autoflow',
        ]);
    }
}
