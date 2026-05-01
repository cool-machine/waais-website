<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StartupListingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_startup_listing_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/startup-listings')->assertForbidden();
        $this->postJson('/api/startup-listings', $this->payload())->assertForbidden();
    }

    #[Test]
    public function approved_member_can_submit_a_startup_listing(): void
    {
        $member = $this->makeMember();
        Sanctum::actingAs($member);

        $response = $this->postJson('/api/startup-listings', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'AutoFlow AI')
            ->assertJsonPath('data.approval_status', ApprovalStatus::Submitted->value)
            ->assertJsonPath('data.content_status', ContentStatus::PendingReview->value)
            ->assertJsonPath('data.visibility', ContentVisibility::Public->value);

        $listing = StartupListing::find($response->json('data.id'));

        $this->assertSame($member->id, $listing->owner_id);
        $this->assertNotNull($listing->submitted_at);
        $this->assertSame(['Daniel Reed', 'Priya Patel'], $listing->founders);

        $this->assertDatabaseHas('startup_listing_revisions', [
            'startup_listing_id' => $listing->id,
            'actor_id' => $member->id,
            'change_note' => 'submitted',
        ]);
    }

    #[Test]
    public function listing_submission_validates_required_fields(): void
    {
        Sanctum::actingAs($this->makeMember());

        $this->postJson('/api/startup-listings', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'tagline', 'description', 'industry']);
    }

    #[Test]
    public function member_can_list_only_their_own_listings(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();

        StartupListing::create($this->payload([
            'owner_id' => $member->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]));
        StartupListing::create($this->payload([
            'owner_id' => $other->id,
            'approval_status' => ApprovalStatus::Submitted,
            'name' => 'Other Co',
            'tagline' => 'Not yours',
        ]));

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/startup-listings')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame('AutoFlow AI', $response->json('data.0.name'));
    }

    #[Test]
    public function member_cannot_show_another_members_listing(): void
    {
        $member = $this->makeMember();
        $other = $this->makeMember();

        $listing = StartupListing::create($this->payload([
            'owner_id' => $other->id,
            'approval_status' => ApprovalStatus::Submitted,
        ]));

        Sanctum::actingAs($member);

        $this->getJson('/api/startup-listings/'.$listing->id)->assertForbidden();
    }

    #[Test]
    public function member_can_update_listing_and_revision_history_records_changes(): void
    {
        $member = $this->makeMember();
        Sanctum::actingAs($member);

        $listing = StartupListing::create($this->payload([
            'owner_id' => $member->id,
            'approval_status' => ApprovalStatus::Submitted,
            'submitted_at' => now()->subDay(),
        ]));

        $payload = $this->payload(['tagline' => 'Renamed tagline']);

        $this->patchJson('/api/startup-listings/'.$listing->id, $payload)
            ->assertOk()
            ->assertJsonPath('data.tagline', 'Renamed tagline')
            ->assertJsonPath('data.approval_status', ApprovalStatus::Submitted->value)
            ->assertJsonPath('data.content_status', ContentStatus::PendingReview->value);

        $this->assertDatabaseHas('startup_listing_revisions', [
            'startup_listing_id' => $listing->id,
            'actor_id' => $member->id,
            'change_note' => 'updated',
        ]);
    }

    #[Test]
    public function member_cannot_edit_an_approved_listing(): void
    {
        $member = $this->makeMember();
        Sanctum::actingAs($member);

        $listing = StartupListing::create($this->payload([
            'owner_id' => $member->id,
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
            'approved_at' => now()->subDay(),
        ]));

        $this->patchJson('/api/startup-listings/'.$listing->id, $this->payload(['tagline' => 'Should not change']))
            ->assertStatus(409);
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'AutoFlow AI',
            'tagline' => 'Workflow automation for B2B teams.',
            'description' => 'AutoFlow AI helps operations teams orchestrate end-to-end workflows with LLMs in the loop.',
            'website_url' => 'https://autoflow.example.com',
            'logo_url' => 'https://autoflow.example.com/logo.png',
            'industry' => 'AI Engineering',
            'stage' => 'Seed',
            'location' => 'New York, NY',
            'founders' => ['Daniel Reed', 'Priya Patel'],
            'submitter_role' => 'Cofounder & CEO',
            'linkedin_url' => 'https://www.linkedin.com/company/autoflow',
        ], $overrides);
    }
}
