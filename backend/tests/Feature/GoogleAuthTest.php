<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function google_callback_creates_pending_user_and_logs_them_in(): void
    {
        $this->mockGoogleUser($this->socialiteUser());

        $this->get('/auth/google/callback')
            ->assertRedirect('http://127.0.0.1:5174/app/pending');

        $user = User::query()->where('email', 'applicant@example.com')->sole();

        $this->assertAuthenticatedAs($user);
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('Ada', $user->first_name);
        $this->assertSame('Lovelace', $user->last_name);
        $this->assertSame(ApprovalStatus::Submitted, $user->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $user->permission_role);
        $this->assertFalse($user->canAccessMemberAreas());
    }

    #[Test]
    public function google_callback_links_existing_unlinked_user_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'applicant@example.com',
            'google_id' => null,
            'approval_status' => ApprovalStatus::None,
            'permission_role' => PermissionRole::Public,
        ]);

        $this->mockGoogleUser($this->socialiteUser());

        $this->get('/auth/google/callback')
            ->assertRedirect('http://127.0.0.1:5174/app/pending');

        $existing->refresh();

        $this->assertAuthenticatedAs($existing);
        $this->assertSame('google-123', $existing->google_id);
        $this->assertSame(ApprovalStatus::Submitted, $existing->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $existing->permission_role);
    }

    #[Test]
    public function google_callback_does_not_downgrade_approved_members(): void
    {
        $member = User::factory()->create([
            'email' => 'member@example.com',
            'google_id' => 'google-456',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $this->mockGoogleUser($this->socialiteUser(
            id: 'google-456',
            email: 'member@example.com',
            name: 'Grace Hopper',
        ));

        $this->get('/auth/google/callback')
            ->assertRedirect('http://127.0.0.1:5174/app/dashboard');

        $member->refresh();

        $this->assertAuthenticatedAs($member);
        $this->assertSame(ApprovalStatus::Approved, $member->approval_status);
        $this->assertSame(PermissionRole::Member, $member->permission_role);
    }

    #[Test]
    public function google_callback_prefers_safe_intended_frontend_path(): void
    {
        $this->mockGoogleUser($this->socialiteUser());

        $this->withSession(['auth.intended_frontend_path' => '/membership'])
            ->get('/auth/google/callback')
            ->assertRedirect('http://127.0.0.1:5174/membership');
    }

    #[Test]
    public function google_callback_rejects_email_already_linked_to_different_google_account(): void
    {
        User::factory()->create([
            'email' => 'applicant@example.com',
            'google_id' => 'different-google-id',
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $this->mockGoogleUser($this->socialiteUser());

        $this->get('/auth/google/callback')
            ->assertConflict();

        $this->assertGuest();
    }

    private function mockGoogleUser(SocialiteUser $user): void
    {
        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($user);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }

    private function socialiteUser(
        string $id = 'google-123',
        string $email = 'applicant@example.com',
        string $name = 'Ada Lovelace',
    ): SocialiteUser {
        return (new SocialiteUser)
            ->setRaw([
                'sub' => $id,
                'email' => $email,
                'given_name' => strtok($name, ' '),
                'family_name' => substr(strstr($name, ' ') ?: '', 1),
            ])
            ->map([
                'id' => $id,
                'email' => $email,
                'name' => $name,
                'avatar' => 'https://example.com/avatar.png',
            ]);
    }
}
