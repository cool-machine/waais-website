<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicStartupListingApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_index_returns_only_published_public_listings(): void
    {
        $published = $this->makeListing(ContentStatus::Published, ContentVisibility::Public, ['name' => 'Visible Co']);
        $this->makeListing(ContentStatus::Draft, ContentVisibility::Public, ['name' => 'Draft Co']);
        $this->makeListing(ContentStatus::PendingReview, ContentVisibility::Public, ['name' => 'Pending Co']);
        $this->makeListing(ContentStatus::Hidden, ContentVisibility::Public, ['name' => 'Hidden Co']);
        $this->makeListing(ContentStatus::Archived, ContentVisibility::Public, ['name' => 'Archived Co']);
        $this->makeListing(ContentStatus::Published, ContentVisibility::MembersOnly, ['name' => 'Members Co']);
        $this->makeListing(ContentStatus::Published, ContentVisibility::Mixed, ['name' => 'Mixed Co']);

        $response = $this->getJson('/api/public/startup-listings')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertSame($published->id, $response->json('data.0.id'));
        $this->assertSame('Visible Co', $response->json('data.0.name'));
    }

    #[Test]
    public function anonymous_show_returns_a_published_public_listing(): void
    {
        $listing = $this->makeListing(ContentStatus::Published, ContentVisibility::Public);

        $this->getJson('/api/public/startup-listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.name', 'AutoFlow AI');
    }

    #[Test]
    public function anonymous_show_returns_404_for_non_published_listing(): void
    {
        $draft = $this->makeListing(ContentStatus::Draft, ContentVisibility::Public);
        $pending = $this->makeListing(ContentStatus::PendingReview, ContentVisibility::Public);
        $hidden = $this->makeListing(ContentStatus::Hidden, ContentVisibility::Public);
        $archived = $this->makeListing(ContentStatus::Archived, ContentVisibility::Public);

        $this->getJson('/api/public/startup-listings/'.$draft->id)->assertNotFound();
        $this->getJson('/api/public/startup-listings/'.$pending->id)->assertNotFound();
        $this->getJson('/api/public/startup-listings/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/public/startup-listings/'.$archived->id)->assertNotFound();
    }

    #[Test]
    public function anonymous_show_returns_404_for_members_only_listing(): void
    {
        $membersOnly = $this->makeListing(ContentStatus::Published, ContentVisibility::MembersOnly);

        $this->getJson('/api/public/startup-listings/'.$membersOnly->id)
            ->assertNotFound();
    }

    #[Test]
    public function anonymous_show_returns_404_for_unknown_id(): void
    {
        $this->getJson('/api/public/startup-listings/9999')->assertNotFound();
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        $listing = $this->makeListing(ContentStatus::Published, ContentVisibility::Public, [
            'review_notes' => 'INTERNAL — should never leak',
            'submitter_role' => 'CEO',
        ]);

        $response = $this->getJson('/api/public/startup-listings/'.$listing->id)->assertOk();
        $payload = $response->json('data');

        // Allowlist — exact set of fields callers should rely on.
        $expected = [
            'id', 'name', 'tagline', 'description', 'website_url', 'logo_url',
            'industry', 'stage', 'location', 'founders', 'linkedin_url', 'approved_at',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing public field: {$field}");
        }

        $actualKeys = array_keys($payload);
        sort($actualKeys);
        $expectedKeys = $expected;
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Public projection key set drifted from the documented allowlist.');

        // Denylist — internal / review / ownership fields must never appear.
        $forbidden = [
            'review_notes', 'submitter_role', 'owner_id', 'owner',
            'reviewed_by', 'reviewer', 'reviewed_at', 'submitted_at',
            'rejected_at', 'approval_status', 'content_status', 'visibility',
            'created_at', 'updated_at', 'revisions',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $payload, "Public projection leaked: {$field}");
        }
    }

    #[Test]
    public function index_paginates_with_default_per_page(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makeListing(ContentStatus::Published, ContentVisibility::Public, [
                'name' => 'Listing '.$i,
            ]);
        }

        $response = $this->getJson('/api/public/startup-listings')->assertOk();

        $this->assertCount(12, $response->json('data'));
        $this->assertSame(15, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));
    }

    #[Test]
    public function index_respects_per_page_within_limits(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeListing(ContentStatus::Published, ContentVisibility::Public, [
                'name' => 'Listing '.$i,
            ]);
        }

        $this->getJson('/api/public/startup-listings?per_page=3')
            ->assertOk()
            ->assertJsonPath('per_page', 3);

        $this->getJson('/api/public/startup-listings?per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);

        $this->getJson('/api/public/startup-listings?per_page=999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeListing(ContentStatus $contentStatus, ContentVisibility $visibility, array $overrides = []): StartupListing
    {
        $owner = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $approvalStatus = match ($contentStatus) {
            ContentStatus::Published => ApprovalStatus::Approved,
            ContentStatus::Hidden => ApprovalStatus::Rejected,
            ContentStatus::Archived => ApprovalStatus::Approved,
            ContentStatus::PendingReview => ApprovalStatus::Submitted,
            ContentStatus::Draft => ApprovalStatus::NeedsMoreInfo,
        };

        return StartupListing::create(array_replace([
            'owner_id' => $owner->id,
            'approval_status' => $approvalStatus,
            'content_status' => $contentStatus,
            'visibility' => $visibility,
            'submitted_at' => now(),
            'approved_at' => $contentStatus === ContentStatus::Published ? now() : null,
            'name' => 'AutoFlow AI',
            'tagline' => 'Workflow automation for B2B teams.',
            'description' => 'AutoFlow AI helps ops teams orchestrate workflows with LLMs.',
            'website_url' => 'https://autoflow.example.com',
            'logo_url' => 'https://autoflow.example.com/logo.png',
            'industry' => 'AI Engineering',
            'stage' => 'Seed',
            'location' => 'New York, NY',
            'founders' => ['Daniel Reed'],
            'submitter_role' => 'Cofounder & CEO',
            'linkedin_url' => 'https://www.linkedin.com/company/autoflow',
        ], $overrides));
    }
}
