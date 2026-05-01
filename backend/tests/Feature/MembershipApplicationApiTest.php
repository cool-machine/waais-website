<?php

namespace Tests\Feature;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function authenticated_user_can_submit_membership_application(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/membership-application', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.approval_status', ApprovalStatus::Submitted->value)
            ->assertJsonPath('data.email', 'applicant@example.com');

        $application = MembershipApplication::query()->sole();

        $this->assertSame($user->id, $application->applicant_id);
        $this->assertSame(ApprovalStatus::Submitted, $application->approval_status);
        $this->assertNotNull($application->submitted_at);
        $this->assertDatabaseHas('application_revisions', [
            'membership_application_id' => $application->id,
            'actor_id' => $user->id,
            'change_note' => 'submitted',
        ]);

        $user->refresh();
        $this->assertSame(ApprovalStatus::Submitted, $user->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $user->permission_role);
        $this->assertSame(AffiliationType::Alumni, $user->affiliation_type);
    }

    #[Test]
    public function authenticated_user_can_update_their_application_and_revision_history_is_recorded(): void
    {
        $user = User::factory()->create();
        $application = MembershipApplication::create($this->payload([
            'applicant_id' => $user->id,
            'approval_status' => ApprovalStatus::Submitted,
            'experience_summary' => 'Original answer',
            'submitted_at' => now(),
        ]));

        Sanctum::actingAs($user);

        $this->patchJson('/api/membership-application', $this->payload([
            'experience_summary' => 'Updated answer',
        ]))
            ->assertOk()
            ->assertJsonPath('data.experience_summary', 'Updated answer');

        $application->refresh();

        $this->assertSame('Updated answer', $application->experience_summary);
        $this->assertSame(['experience_summary'], $application->revisions()->latest()->first()->changed_fields);
    }

    #[Test]
    public function rejected_applicant_can_reapply(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Rejected,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        MembershipApplication::create($this->payload([
            'applicant_id' => $user->id,
            'approval_status' => ApprovalStatus::Rejected,
            'reviewed_at' => now(),
            'review_notes' => 'Not enough information.',
            'submitted_at' => now()->subDay(),
        ]));

        Sanctum::actingAs($user);

        $this->postJson('/api/membership-application/reapply', $this->payload([
            'experience_summary' => 'More information.',
        ]))
            ->assertOk()
            ->assertJsonPath('data.approval_status', ApprovalStatus::Submitted->value)
            ->assertJsonPath('data.review_notes', null);

        $user->refresh();

        $this->assertSame(ApprovalStatus::Submitted, $user->approval_status);
        $this->assertDatabaseHas('application_revisions', ['change_note' => 'reapplied']);
    }

    #[Test]
    public function approved_application_cannot_be_edited_by_applicant(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        MembershipApplication::create($this->payload([
            'applicant_id' => $user->id,
            'approval_status' => ApprovalStatus::Approved,
            'submitted_at' => now(),
        ]));

        Sanctum::actingAs($user);

        $this->patchJson('/api/membership-application', $this->payload([
            'experience_summary' => 'Should fail.',
        ]))->assertConflict();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'affiliation_type' => AffiliationType::Alumni->value,
            'email' => 'applicant@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone_whatsapp' => '+1 555 0100',
            'is_alumnus' => true,
            'school_affiliation' => 'Wharton MBA',
            'graduation_year' => 2015,
            'inviter_name' => null,
            'primary_location' => 'New York',
            'secondary_location' => null,
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'experience_summary' => 'I build AI products.',
            'expertise_summary' => 'Product, applied ML, strategy.',
            'industries_to_add_value' => ['Finance', 'AI Engineering'],
            'industries_to_extend_expertise' => ['Healthcare'],
            'availability' => 'Monthly',
            'gender' => null,
            'age' => null,
        ], $overrides);
    }
}
