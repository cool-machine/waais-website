<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use App\Notifications\EmailSignInLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailAuthTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function requesting_an_email_link_creates_pending_user_and_sends_notification(): void
    {
        Notification::fake();

        $this->postJson('/api/auth/email-link', [
            'email' => 'Applicant@Example.com',
            'next' => '/membership',
        ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user = User::query()->where('email', 'applicant@example.com')->sole();

        $this->assertSame(ApprovalStatus::Submitted, $user->approval_status);
        $this->assertSame(PermissionRole::PendingUser, $user->permission_role);

        Notification::assertSentTo($user, EmailSignInLink::class, function (EmailSignInLink $notification) use ($user): bool {
            return str_contains($notification->url, '/auth/email/callback/'.$user->id)
                && str_contains($notification->url, 'signature=');
        });
    }

    #[Test]
    public function requesting_an_email_link_does_not_downgrade_approved_members(): void
    {
        Notification::fake();

        $member = User::factory()->create([
            'email' => 'member@example.com',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $this->postJson('/api/auth/email-link', [
            'email' => 'member@example.com',
            'next' => '/membership',
        ])->assertOk();

        $member->refresh();

        $this->assertSame(ApprovalStatus::Approved, $member->approval_status);
        $this->assertSame(PermissionRole::Member, $member->permission_role);
        Notification::assertSentTo($member, EmailSignInLink::class);
    }

    #[Test]
    public function signed_email_callback_verifies_email_logs_in_and_redirects_to_safe_next_path(): void
    {
        $user = User::factory()->unverified()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $url = URL::temporarySignedRoute('auth.email.callback', now()->addMinutes(30), [
            'user' => $user->id,
            'next' => '/membership',
        ]);

        $this->get($url)
            ->assertRedirect('http://127.0.0.1:5174/membership');

        $user->refresh();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
    }

    #[Test]
    public function email_callback_rejects_tampered_links(): void
    {
        $user = User::factory()->create();

        $url = URL::temporarySignedRoute('auth.email.callback', now()->addMinutes(30), [
            'user' => $user->id,
            'next' => '/membership',
        ]);

        $this->get($url.'&next=https://evil.example')
            ->assertForbidden();
    }
}
