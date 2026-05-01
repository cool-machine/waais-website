<?php

namespace Tests\Feature;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\AuditLog;
use App\Models\MembershipApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminMembershipApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_admin_application_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/applications')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_admin_application_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/applications')->assertForbidden();
    }

    #[Test]
    public function admin_can_list_application_queue_filtered_by_status(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $submitted = $this->makeApplication(ApprovalStatus::Submitted);
        $this->makeApplication(ApprovalStatus::Approved);
        $this->makeApplication(ApprovalStatus::Rejected);

        $response = $this->getJson('/api/admin/applications?status=submitted')
            ->assertOk()
            ->assertJsonPath('data.0.id', $submitted->id);

        $this->assertCount(1, $response->json('data'));
    }

    #[Test]
    public function admin_can_view_a_single_application_with_revisions_and_reviewer(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $application = $this->makeApplication(ApprovalStatus::Submitted);

        $this->getJson('/api/admin/applications/'.$application->id)
            ->assertOk()
            ->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.applicant.id', $application->applicant_id);
    }

    #[Test]
    public function admin_can_approve_a_submitted_application_and_promote_pending_user_to_member(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $application = $this->makeApplication(ApprovalStatus::Submitted, AffiliationType::Alumni);
        $applicant = $application->applicant;

        $this->postJson('/api/admin/applications/'.$application->id.'/approve', [
            'review_notes' => 'Welcomed to the studio.',
        ])
            ->assertOk()
            ->assertJsonPath('data.approval_status', ApprovalStatus::Approved->value);

        $application->refresh();
        $applicant->refresh();

        $this->assertSame(ApprovalStatus::Approved, $application->approval_status);
        $this->assertSame($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
        $this->assertSame('Welcomed to the studio.', $application->review_notes);

        $this->assertSame(ApprovalStatus::Approved, $applicant->approval_status);
        $this->assertSame(PermissionRole::Member, $applicant->permission_role);
        $this->assertSame(AffiliationType::Alumni, $applicant->affiliation_type);
        $this->assertNotNull($applicant->approved_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'applications.approve',
            'auditable_type' => MembershipApplication::class,
            'auditable_id' => $application->id,
        ]);
    }

    #[Test]
    public function approving_an_application_does_not_downgrade_an_existing_super_admin(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $superAdmin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);

        $application = MembershipApplication::create($this->payload([
            'applicant_id' => $superAdmin->id,
            'approval_status' => ApprovalStatus::Submitted,
            'submitted_at' => now(),
        ]));

        $this->postJson('/api/admin/applications/'.$application->id.'/approve')
            ->assertOk();

        $superAdmin->refresh();

        $this->assertSame(PermissionRole::SuperAdmin, $superAdmin->permission_role);
        $this->assertSame(ApprovalStatus::Approved, $superAdmin->approval_status);
    }

    #[Test]
    public function admin_can_reject_application_and_review_notes_are_required(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $application = $this->makeApplication(ApprovalStatus::Submitted);
        $applicant = $application->applicant;

        // notes required
        $this->postJson('/api/admin/applications/'.$application->id.'/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_notes']);

        $this->postJson('/api/admin/applications/'.$application->id.'/reject', [
            'review_notes' => 'Insufficient affiliation evidence.',
        ])->assertOk();

        $application->refresh();
        $applicant->refresh();

        $this->assertSame(ApprovalStatus::Rejected, $application->approval_status);
        $this->assertSame(ApprovalStatus::Rejected, $applicant->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $applicant->permission_role);
        $this->assertNotNull($applicant->rejected_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'applications.reject',
        ]);
    }

    #[Test]
    public function admin_can_request_more_info_and_review_notes_are_required(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $application = $this->makeApplication(ApprovalStatus::Submitted);
        $applicant = $application->applicant;

        $this->postJson('/api/admin/applications/'.$application->id.'/request-info', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_notes']);

        $this->postJson('/api/admin/applications/'.$application->id.'/request-info', [
            'review_notes' => 'Please clarify Wharton affiliation.',
        ])->assertOk();

        $application->refresh();
        $applicant->refresh();

        $this->assertSame(ApprovalStatus::NeedsMoreInfo, $application->approval_status);
        $this->assertSame(ApprovalStatus::NeedsMoreInfo, $applicant->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $applicant->permission_role);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'applications.request_info',
        ]);
    }

    #[Test]
    public function super_admin_can_use_admin_application_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]));

        $this->getJson('/api/admin/applications')->assertOk();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);
    }

    private function makeApplication(ApprovalStatus $status, ?AffiliationType $affiliation = null): MembershipApplication
    {
        $applicant = User::factory()->create([
            'approval_status' => $status === ApprovalStatus::Approved ? ApprovalStatus::Approved : ApprovalStatus::Submitted,
            'permission_role' => $status === ApprovalStatus::Approved ? PermissionRole::Member : PermissionRole::PendingUser,
        ]);

        return MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => $status,
            'affiliation_type' => $affiliation ?? AffiliationType::Alumni,
            'submitted_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'affiliation_type' => AffiliationType::Alumni,
            'email' => 'applicant@example.com',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'phone_whatsapp' => null,
            'is_alumnus' => true,
            'school_affiliation' => 'Wharton MBA',
            'graduation_year' => 2015,
            'inviter_name' => null,
            'primary_location' => 'New York',
            'secondary_location' => null,
            'linkedin_url' => 'https://www.linkedin.com/in/example',
            'experience_summary' => 'I build AI products.',
            'expertise_summary' => 'Product, applied ML.',
            'industries_to_add_value' => ['Finance'],
            'industries_to_extend_expertise' => ['Healthcare'],
            'availability' => 'Monthly',
            'gender' => null,
            'age' => null,
        ], $overrides);
    }
}
