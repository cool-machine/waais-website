<?php

namespace Tests\Feature;

use App\Enums\AffiliationType;
use App\Enums\ApprovalStatus;
use App\Enums\PermissionRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Allowlisted projection. Drift breaks the build via
     * projection_excludes_internal_fields.
     *
     * @var list<string>
     */
    private const PROJECTION = [
        'id',
        'name',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'email_verified_at',
        'avatar_url',
        'approval_status',
        'affiliation_type',
        'permission_role',
        'approved_at',
        'rejected_at',
        'suspended_at',
        'created_at',
    ];

    #[Test]
    public function pending_user_cannot_list_users(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_list_users(): void
    {
        Sanctum::actingAs($this->makeMember());

        $this->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function admin_can_list_users_with_pagination_metadata(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->makeMember();
        $this->makeMember();

        $response = $this->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    #[Test]
    public function admin_can_filter_by_permission_role(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $member = $this->makeMember();
        $superAdmin = $this->makeSuperAdmin();

        $memberIds = collect($this->getJson('/api/admin/users?permission_role=member')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($member->id, $memberIds);
        $this->assertNotContains($superAdmin->id, $memberIds);
    }

    #[Test]
    public function admin_can_filter_by_approval_status(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $approved = $this->makeMember();
        $pending = User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]);

        $pendingIds = collect($this->getJson('/api/admin/users?approval_status=submitted')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($pending->id, $pendingIds);
        $this->assertNotContains($approved->id, $pendingIds);
    }

    #[Test]
    public function admin_can_search_by_email_substring(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $alice = User::factory()->create([
            'name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);
        $bob = User::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]);

        $ids = collect($this->getJson('/api/admin/users?q=alice')->assertOk()->json('data'))
            ->pluck('id')
            ->all();

        $this->assertContains($alice->id, $ids);
        $this->assertNotContains($bob->id, $ids);
    }

    #[Test]
    public function index_validates_per_page_bounds(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/users?per_page=0')->assertUnprocessable();
        $this->getJson('/api/admin/users?per_page=999')->assertUnprocessable();
    }

    #[Test]
    public function admin_can_show_a_user_by_id(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $target = $this->makeMember();

        $this->getJson('/api/admin/users/'.$target->id)
            ->assertOk()
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.email', $target->email)
            ->assertJsonPath('data.permission_role', PermissionRole::Member->value);
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $target = $this->makeMember(['google_id' => 'google-12345']);

        $listKeys = array_keys($this->getJson('/api/admin/users')->json('data.0'));
        sort($listKeys);
        $expected = self::PROJECTION;
        sort($expected);
        $this->assertSame($expected, $listKeys);

        $showKeys = array_keys($this->getJson('/api/admin/users/'.$target->id)->json('data'));
        sort($showKeys);
        $this->assertSame($expected, $showKeys);

        // Defensive: nothing in the projection should leak password / google_id /
        // remember_token even if a future column gets added to the model.
        foreach (['password', 'remember_token', 'google_id'] as $denied) {
            $this->assertNotContains($denied, $listKeys);
            $this->assertNotContains($denied, $showKeys);
        }
    }

    #[Test]
    public function super_admin_can_list_users(): void
    {
        Sanctum::actingAs($this->makeSuperAdmin());

        $this->getJson('/api/admin/users')->assertOk();
    }

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeMember(array $overrides = []): User
    {
        return User::factory()->create(array_replace([
            'approval_status' => ApprovalStatus::Approved,
            'affiliation_type' => AffiliationType::Alumni,
            'permission_role' => PermissionRole::Member,
        ], $overrides));
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]);
    }
}
