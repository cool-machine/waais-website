<?php

namespace Tests\Feature;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApplicationApproved;
use App\Notifications\MembershipApplicationNeedsMoreInfo;
use App\Notifications\MembershipApplicationReceivedByAdmin;
use App\Notifications\MembershipApplicationRejected;
use App\Notifications\MembershipApplicationSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MembershipNotificationsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function submission_sends_thank_you_to_applicant_and_notice_to_admins(): void
    {
        Notification::fake();

        $applicant = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);
        $admin = $this->makeAdmin();
        $superAdmin = $this->makeSuperAdmin();
        $regularMember = $this->makeMember();

        Sanctum::actingAs($applicant);

        $this->postJson('/api/membership-application', $this->payload([
            'privacy_acknowledgement' => true,
        ]))
            ->assertCreated();

        Notification::assertSentTo($applicant, MembershipApplicationSubmitted::class);
        Notification::assertSentTo($admin, MembershipApplicationReceivedByAdmin::class);
        Notification::assertSentTo($superAdmin, MembershipApplicationReceivedByAdmin::class);
        Notification::assertNotSentTo($regularMember, MembershipApplicationReceivedByAdmin::class);
        Notification::assertNotSentTo($applicant, MembershipApplicationReceivedByAdmin::class);
    }

    #[Test]
    public function reapply_sends_thank_you_and_admin_notice(): void
    {
        Notification::fake();

        $applicant = User::factory()->create([
            'approval_status' => ApprovalStatus::Rejected,
            'permission_role' => PermissionRole::PendingUser,
        ]);
        MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => ApprovalStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'reviewed_at' => now()->subDay(),
            'review_notes' => 'Insufficient evidence.',
        ]));
        $admin = $this->makeAdmin();

        Sanctum::actingAs($applicant);

        $this->postJson('/api/membership-application/reapply', $this->payload([
            'experience_summary' => 'Extra info now.',
            'privacy_acknowledgement' => true,
        ]))->assertOk();

        Notification::assertSentTo($applicant, MembershipApplicationSubmitted::class);
        Notification::assertSentTo($admin, MembershipApplicationReceivedByAdmin::class);
    }

    #[Test]
    public function update_does_not_fire_any_notification(): void
    {
        Notification::fake();

        $applicant = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);
        MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => ApprovalStatus::Submitted,
            'submitted_at' => now()->subHour(),
        ]));
        $this->makeAdmin();

        Sanctum::actingAs($applicant);

        $this->patchJson('/api/membership-application', $this->payload([
            'experience_summary' => 'Tweak.',
        ]))->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function approve_sends_approval_email_to_applicant_only(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $application = $this->makeApplication(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/applications/'.$application->id.'/approve', [
            'review_notes' => 'Welcome aboard.',
        ])->assertOk();

        Notification::assertSentTo($application->applicant, MembershipApplicationApproved::class);
        Notification::assertNotSentTo($application->applicant, MembershipApplicationNeedsMoreInfo::class);
        Notification::assertNotSentTo($application->applicant, MembershipApplicationRejected::class);
        Notification::assertNotSentTo($admin, MembershipApplicationApproved::class);
    }

    #[Test]
    public function request_info_sends_needs_more_info_email_to_applicant(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $application = $this->makeApplication(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/applications/'.$application->id.'/request-info', [
            'review_notes' => 'Please clarify your Wharton affiliation.',
        ])->assertOk();

        Notification::assertSentTo($application->applicant, MembershipApplicationNeedsMoreInfo::class);
        Notification::assertNotSentTo($application->applicant, MembershipApplicationApproved::class);
        Notification::assertNotSentTo($application->applicant, MembershipApplicationRejected::class);
    }

    #[Test]
    public function reject_does_not_send_email_by_default(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $application = $this->makeApplication(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/applications/'.$application->id.'/reject', [
            'review_notes' => 'Not aligned.',
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function reject_with_send_email_flag_sends_rejection_email(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $application = $this->makeApplication(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/applications/'.$application->id.'/reject', [
            'review_notes' => 'Not aligned.',
            'send_email' => true,
        ])->assertOk();

        Notification::assertSentTo($application->applicant, MembershipApplicationRejected::class);
    }

    #[Test]
    public function reject_with_send_email_false_sends_no_email(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $application = $this->makeApplication(ApprovalStatus::Submitted);

        Sanctum::actingAs($admin);

        $this->postJson('/api/admin/applications/'.$application->id.'/reject', [
            'review_notes' => 'Not aligned.',
            'send_email' => false,
        ])->assertOk();

        Notification::assertNothingSent();
    }

    #[Test]
    public function notification_uses_mail_channel_only(): void
    {
        $applicant = User::factory()->create();
        $application = MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => ApprovalStatus::Submitted,
            'submitted_at' => now(),
        ]));

        $this->assertSame(['mail'], (new MembershipApplicationSubmitted($application))->via($applicant));
        $this->assertSame(['mail'], (new MembershipApplicationApproved($application))->via($applicant));
        $this->assertSame(['mail'], (new MembershipApplicationNeedsMoreInfo($application))->via($applicant));
        $this->assertSame(['mail'], (new MembershipApplicationRejected($application))->via($applicant));
        $this->assertSame(['mail'], (new MembershipApplicationReceivedByAdmin($application))->via($applicant));
    }

    #[Test]
    public function approval_mail_includes_applicant_first_name_and_review_notes(): void
    {
        $applicant = User::factory()->create();
        $application = MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => ApprovalStatus::Approved,
            'first_name' => 'Ada',
            'review_notes' => 'Welcome to the studio.',
            'submitted_at' => now()->subHour(),
            'reviewed_at' => now(),
        ]));

        $mail = (new MembershipApplicationApproved($application))->toMail($applicant);
        $rendered = json_encode($mail->toArray());

        $this->assertSame('Welcome to the Wharton Alumni AI Studio', $mail->subject);
        $this->assertStringContainsString('Hi Ada,', $rendered);
        $this->assertStringContainsString('Welcome to the studio.', $rendered);
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

    private function makeApplication(ApprovalStatus $status): MembershipApplication
    {
        $applicant = User::factory()->create([
            'approval_status' => $status === ApprovalStatus::Approved ? ApprovalStatus::Approved : ApprovalStatus::Submitted,
            'permission_role' => $status === ApprovalStatus::Approved ? PermissionRole::Member : PermissionRole::PendingUser,
        ]);

        return MembershipApplication::create($this->payload([
            'applicant_id' => $applicant->id,
            'approval_status' => $status,
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
            'affiliation_type' => AffiliationType::Alumni->value,
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
