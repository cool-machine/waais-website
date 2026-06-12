<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\MembershipApplication;
use App\Models\User;
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
        ], $overrides);
    }

    #[Test]
    public function visitor_can_register_an_account(): void
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

        // No application yet: the form is filled in after verification.
        $this->assertSame(0, MembershipApplication::query()->where('applicant_id', $user->id)->count());

        Notification::assertSentTo($user, VerifyRegistrationEmail::class);
    }

    #[Test]
    public function registration_validates_password_and_unique_email(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload(['password_confirmation' => 'mismatch']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', $this->registrationPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        User::factory()->create(['email' => 'applicant@example.com']);

        $this->postJson('/api/auth/register', $this->registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function email_verification_marks_user_verified_and_signs_them_in(): void
    {
        $this->postJson('/api/auth/register', $this->registrationPayload())->assertCreated();
        $user = User::query()->where('email', 'applicant@example.com')->firstOrFail();

        $link = URL::temporarySignedRoute('auth.register.verify', now()->addHour(), ['user' => $user->id]);

        $this->get($link)->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        // The /api/user projection exposes the verification flag the SPA
        // uses to unlock the application form.
        $this->getJson('/api/user')->assertOk()->assertJson(['email_verified' => true]);
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
    public function forgot_password_sends_reset_link_pointing_at_the_spa(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->postJson('/api/auth/forgot-password', ['email' => 'member@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        Notification::assertSentTo($user, \Illuminate\Auth\Notifications\ResetPassword::class, function ($notification) use ($user): bool {
            $url = ($notification::$createUrlCallback)($user, $notification->token);

            return str_contains($url, '/reset-password?token=')
                && str_contains($url, 'email=member%40example.com');
        });

        // Unknown emails get the same response, with no mail sent.
        $this->postJson('/api/auth/forgot-password', ['email' => 'ghost@example.com'])
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    #[Test]
    public function password_can_be_reset_with_a_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('old-password-123'),
        ]);

        $token = \Illuminate\Support\Facades\Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'member@example.com',
            'token' => $token,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'brand-new-password',
        ])->assertOk();

        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function password_reset_rejects_invalid_tokens(): void
    {
        User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('old-password-123'),
        ]);

        $this->postJson('/api/auth/reset-password', [
            'email' => 'member@example.com',
            'token' => 'bogus-token',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'old-password-123',
        ])->assertOk();
    }

    #[Test]
    public function login_resumes_pending_discourse_sso_for_members(): void
    {
        $member = User::factory()->create([
            'email' => 'member@example.com',
            'password' => bcrypt('correct-horse-battery'),
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $ssoUrl = 'https://api.whartonai.studio/discourse/sso?sso=abc&sig=def';

        $this->from('http://localhost')
            ->withSession(['discourse.sso.intended_url' => $ssoUrl])
            ->postJson('/api/auth/login', [
                'email' => 'member@example.com',
                'password' => 'correct-horse-battery',
            ])->assertOk()->assertJson(['ok' => true, 'redirect' => $ssoUrl]);

        // Non-members do not get bounced into the forum handshake.
        $pending = User::factory()->create([
            'email' => 'pending@example.com',
            'password' => bcrypt('correct-horse-battery'),
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $this->from('http://localhost')
            ->withSession(['discourse.sso.intended_url' => $ssoUrl])
            ->postJson('/api/auth/login', [
                'email' => 'pending@example.com',
                'password' => 'correct-horse-battery',
            ])->assertOk()->assertJson(['ok' => true, 'redirect' => null]);
    }

    #[Test]
    public function submitting_an_application_does_not_demote_admins(): void
    {
        $superAdmin = User::factory()->create([
            'email' => 'boss@example.com',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin)->postJson('/api/membership-application', [
            'email' => 'boss@example.com',
            'first_name' => 'Big',
            'last_name' => 'Boss',
            'is_alumnus' => true,
            'affiliation_type' => 'alumni',
            'privacy_acknowledgement' => true,
        ])->assertCreated();

        $superAdmin->refresh();
        $this->assertSame(PermissionRole::SuperAdmin, $superAdmin->permission_role);
        $this->assertSame(ApprovalStatus::Approved, $superAdmin->approval_status);
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
