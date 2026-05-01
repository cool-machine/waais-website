<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\StartupListing;
use App\Models\User;
use App\Notifications\StartupListingApproved;
use App\Notifications\StartupListingNeedsMoreInfo;
use App\Notifications\StartupListingReceivedByAdmin;
use App\Notifications\StartupListingRejected;
use App\Notifications\StartupListingSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StartupListingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function submission_sends_thank_you_to_owner_and_notice_to_admins(): void
    {
        Notification::fake();

        $member = $this->makeMember();
        $admin = $this->makeAdmin();
        $superAdmin = $this->makeSuperAdmin();
        $otherMember = $this->makeMember();

        Sanctum::actingAs($member);

        $this->postJson('/api/startup-listings', $this->payload())->assertCreated();

        Notification::assertSentTo($member, StartupListingSubmitted::class);
        Notification::assertSentTo($admin, StartupListingReceivedByAdmin::class);
        Notification::assertSentTo($superAdmin, StartupListingReceivedByAdmin::class);
        Notification::assertNotSentTo($otherMember, StartupListingReceivedByAdmin::class);
        Notification::assertNotSentTo($member, StartupListingReceivedByAdmin::class);
    }

    #[Test]
    public function update_does_not_fire_any_notification(): void
    {
        Notification::fake();

        $member = $this->makeMember();
        $listing = StartupListing::create($this->listingAttributes([
            'owner_id' => $member->id,
            'approval_status' => ApprovalStatus::Submitted,
            'content_status' => ContentStatus::PendingReview,
            'submitted_at' => now()->subHour(),
        ]));
        $this->makeAdmin();

        Sanctum::actingAs($member);

        $this->patchJson('/api/startup-listings/'.$listing->id, $this->payload([
            'tagline' => 'Updated tagline',
        ]))->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function approve_sends_approval_email_to_owner(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $listing = $this->makeListing(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/approve', [
            'review_notes' => 'Looks great.',
        ])->assertOk();

        Notification::assertSentTo($listing->owner, StartupListingApproved::class);
        Notification::assertNotSentTo($listing->owner, StartupListingNeedsMoreInfo::class);
        Notification::assertNotSentTo($listing->owner, StartupListingRejected::class);
        Notification::assertNotSentTo($admin, StartupListingApproved::class);
    }

    #[Test]
    public function request_info_sends_needs_more_info_email_to_owner(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $listing = $this->makeListing(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/request-info', [
            'review_notes' => 'Please add a website URL.',
        ])->assertOk();

        Notification::assertSentTo($listing->owner, StartupListingNeedsMoreInfo::class);
        Notification::assertNotSentTo($listing->owner, StartupListingApproved::class);
        Notification::assertNotSentTo($listing->owner, StartupListingRejected::class);
    }

    #[Test]
    public function reject_does_not_send_email_by_default(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $listing = $this->makeListing(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/reject', [
            'review_notes' => 'Not a fit.',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function reject_with_send_email_flag_sends_rejection_email(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $listing = $this->makeListing(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/reject', [
            'review_notes' => 'Not a fit.',
            'send_email' => true,
        ])->assertOk();

        Notification::assertSentTo($listing->owner, StartupListingRejected::class);
    }

    #[Test]
    public function reject_with_send_email_false_sends_no_email(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $listing = $this->makeListing(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/startup-listings/'.$listing->id.'/reject', [
            'review_notes' => 'Not a fit.',
            'send_email' => false,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function notification_uses_mail_channel_only(): void
    {
        $owner = User::factory()->create();
        $listing = StartupListing::create($this->listingAttributes([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Submitted,
            'content_status' => ContentStatus::PendingReview,
            'submitted_at' => now(),
        ]));

        $this->assertSame(['mail'], (new StartupListingSubmitted($listing))->via($owner));
        $this->assertSame(['mail'], (new StartupListingApproved($listing))->via($owner));
        $this->assertSame(['mail'], (new StartupListingNeedsMoreInfo($listing))->via($owner));
        $this->assertSame(['mail'], (new StartupListingRejected($listing))->via($owner));
        $this->assertSame(['mail'], (new StartupListingReceivedByAdmin($listing))->via($owner));
    }

    #[Test]
    public function approval_mail_includes_listing_name_and_review_notes(): void
    {
        $owner = User::factory()->create();
        $listing = StartupListing::create($this->listingAttributes([
            'owner_id' => $owner->id,
            'approval_status' => ApprovalStatus::Approved,
            'content_status' => ContentStatus::Published,
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
            'approved_at' => now(),
            'review_notes' => 'Approved with minor edits.',
        ]));

        $mail = (new StartupListingApproved($listing))->toMail($owner);
        $rendered = json_encode($mail->toArray());

        $this->assertSame('Your startup listing is live', $mail->subject);
        $this->assertStringContainsString('AutoFlow AI', $rendered);
        $this->assertStringContainsString('Approved with minor edits.', $rendered);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);
    }

    private function makeMember(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);
    }

    private function makeListing(ApprovalStatus $status): StartupListing
    {
        $owner = $this->makeMember();

        $contentStatus = match ($status) {
            ApprovalStatus::Approved => ContentStatus::Published,
            ApprovalStatus::Rejected => ContentStatus::Hidden,
            ApprovalStatus::NeedsMoreInfo => ContentStatus::Draft,
            default => ContentStatus::PendingReview,
        };

        return StartupListing::create($this->listingAttributes([
            'owner_id' => $owner->id,
            'approval_status' => $status,
            'content_status' => $contentStatus,
            'submitted_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function listingAttributes(array $overrides = []): array
    {
        return array_replace([
            'visibility' => ContentVisibility::Public,
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
        ], $overrides);
    }

    /**
     * Payload for POST/PATCH endpoints (no enum casts, no owner_id).
     *
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
