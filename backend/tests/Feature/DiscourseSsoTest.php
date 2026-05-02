<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscourseSsoTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'discourse-test-secret';
    private const RETURN_URL = 'https://forum.whartonai.studio/session/sso_login';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.discourse.connect_secret', self::SECRET);
        config()->set('services.discourse.url', 'https://forum.whartonai.studio');
        config()->set('app.frontend_url', 'http://127.0.0.1:5174/waais-website');
    }

    #[Test]
    public function guest_with_valid_sso_request_is_sent_to_sign_in_and_request_is_preserved(): void
    {
        $url = $this->signedSsoUrl();

        $response = $this->get($url)
            ->assertRedirect('http://127.0.0.1:5174/waais-website/app/sign-in');

        $this->assertStringContainsString('/discourse/sso?', $response->baseResponse->getSession()->get('discourse.sso.intended_url'));
        $this->assertStringContainsString('sig=', $response->baseResponse->getSession()->get('discourse.sso.intended_url'));
        $this->assertStringContainsString('sso=', $response->baseResponse->getSession()->get('discourse.sso.intended_url'));
    }

    #[Test]
    public function approved_member_receives_signed_discourse_connect_response(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'display_name' => 'Ada L.',
            'email' => 'ada@example.com',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $redirect = $this->actingAs($user)->get($this->signedSsoUrl('nonce-123'))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertStringStartsWith(self::RETURN_URL.'?', $redirect);
        $query = $this->redirectQuery($redirect);

        $this->assertTrue(hash_equals(
            hash_hmac('sha256', $query['sso'], self::SECRET),
            $query['sig'],
        ));

        parse_str(base64_decode($query['sso'], strict: true), $payload);

        $this->assertSame('nonce-123', $payload['nonce']);
        $this->assertSame('ada@example.com', $payload['email']);
        $this->assertSame('waais-user-'.$user->id, $payload['external_id']);
        $this->assertSame('Ada L.', $payload['name']);
        $this->assertSame('ada_l', $payload['username']);
        $this->assertSame('waais_members', $payload['groups']);
        $this->assertSame('false', $payload['admin']);
        $this->assertSame('false', $payload['moderator']);
    }

    #[Test]
    public function super_admin_is_marked_as_discourse_admin_and_member_of_admin_group(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);

        $redirect = $this->actingAs($user)->get($this->signedSsoUrl())
            ->assertRedirect()
            ->headers->get('Location');

        parse_str(base64_decode($this->redirectQuery($redirect)['sso'], strict: true), $payload);

        $this->assertSame('true', $payload['admin']);
        $this->assertSame('true', $payload['moderator']);
        $this->assertSame('waais_members,waais_admins', $payload['groups']);
    }

    #[Test]
    public function pending_user_is_redirected_to_pending_page(): void
    {
        $user = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $this->actingAs($user)->get($this->signedSsoUrl())
            ->assertRedirect('http://127.0.0.1:5174/waais-website/app/pending');
    }

    #[Test]
    public function invalid_signature_is_rejected(): void
    {
        $this->get('/discourse/sso?sso='.urlencode($this->requestPayload()).'&sig=bad-signature')
            ->assertForbidden();
    }

    #[Test]
    public function signed_request_with_non_discourse_return_url_is_rejected(): void
    {
        $this->get($this->signedSsoUrl(returnUrl: 'https://evil.example/session/sso_login'))
            ->assertUnprocessable();
    }

    private function signedSsoUrl(string $nonce = 'abc123', string $returnUrl = self::RETURN_URL): string
    {
        $payload = $this->requestPayload($nonce, $returnUrl);
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        return '/discourse/sso?'.http_build_query([
            'sso' => $payload,
            'sig' => $signature,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private function requestPayload(string $nonce = 'abc123', string $returnUrl = self::RETURN_URL): string
    {
        return base64_encode(http_build_query([
            'nonce' => $nonce,
            'return_sso_url' => $returnUrl,
        ], '', '&', PHP_QUERY_RFC3986));
    }

    /**
     * @return array{sso: string, sig: string}
     */
    private function redirectQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return [
            'sso' => (string) $query['sso'],
            'sig' => (string) $query['sig'],
        ];
    }
}
