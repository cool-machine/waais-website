<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\MembershipApplication;
use App\Models\User;
use App\Notifications\MembershipApplicationReceivedByAdmin;
use App\Notifications\MembershipApplicationSubmitted;
use App\Notifications\VerifyRegistrationEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PasswordRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function registrationPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'applicant@example.com',
            'password' => 'super-secret-pw',
            'password_confirmation' => 'super-secret-pw',
            'first_name' => 'Grace',
            'last_name' => 'Hopper',
            'is_alumnus' => true,
            'affiliation_type' => 'alumni',
            'privacy_acknowledgement' => true,
        ], $overrides);
    }

    #[Test]
    public function visitor_can_register_with_combined_application_form(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/register', $this->registrationPayload())
            ->assertCreated()
            ->assertJson(['ok' => true, 'verification_required' => true]);

        $user = User::query()->where('email', 'applicant@example.com')->firstOrFail();
        $this->assertSame(ApprovalStatus::Draft, $user->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $user->permission_role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->password);

        $application = MembershipApplication::query()->where('applicant_id', $user->id)->firstOrFail();
        $this->assertSame(ApprovalStatus::Draft, $application->approval_status);
        $this->assertNull($application->submitted_at);
        $this->assertNotNull($application->privacy_acknowledged_at);

        Notification::assertSentTo($user, VerifyRegistrationEmail::class);
        Notification::assertNotSentTo($user, MembershipApplicationSubmitted::class);
    }

    #[Test]
    public function registration_requires_privacy_acknowledgement_and_unique_email(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload(['privacy_acknowledgement' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_acknowledgement']);

        User::factory()->create(['email' => 'applicant@example.com']);

        $this->postJson('/api/auth/register', $this->registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function email_verification_submits_application_and_notifies_admins(): void
    {
        Notification::fake();

        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();
        $user = User::query()->where('email', 'applicant@example.com')->firstOrFail();

        $link = URL::temporarySignedRoute('auth.register.verify', now()->addHour(), ['user' => $user->id]);

        $this->get($link)->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(ApprovalStatus::Submitted, $user->approval_status);
        $this->assertAuthenticatedAs($user);

        $application = MembershipApplication::query()->where('applicant_id', $user->id)->firstOrFail();
        $this->assertSame(ApprovalStatus::Submitted, $application->approval_status);
        $this->assertNotNull($application->submitted_at);

        Notification::assertSentTo($user, MembershipApplicationSubmitted::class);
        Notification::assertSentTo($admin, MembershipApplicationReceivedByAdmin::class);
    }

    #[Test]
    public function verification_link_must_be_signed(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->get('/auth/register/verify/'.$user->id)->assertForbidden();
    }

    #[Test]
    public function user_can_login_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('correct-horse-battery'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'correct-horse-battery',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function login_rejects_wrong_password_and_google_only_accounts(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('correct-horse-battery'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        User::factory()->create([
            'email' => 'google-only@example.com',
            'password' => null,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'google-only@example.com',
            'password' => 'anything-at-all',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    #[Test]
    public function resend_verification_is_silent_for_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/resend-verification', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Notification::assertNothingSent();
    }
}
