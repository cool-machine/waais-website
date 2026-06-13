<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminPartnerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_admin_partner_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/partners')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_admin_partner_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/partners')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_a_draft_partner(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/partners', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Cloud Platform Partner')
            ->assertJsonPath('data.content_status', ContentStatus::Draft->value)
            ->assertJsonPath('data.visibility', ContentVisibility::Public->value);

        $partner = Partner::find($response->json('data.id'));
        $this->assertSame($admin->id, $partner->created_by);
        $this->assertNull($partner->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'partners.create',
            'auditable_type' => Partner::class,
            'auditable_id' => $partner->id,
        ]);
    }

    #[Test]
    public function partner_creation_validates_required_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/partners', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'summary', 'description']);
    }

    #[Test]
    public function admin_can_update_a_partner_and_changes_are_audited(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $partner = $this->makePartner();

        $this->patchJson('/api/admin/partners/'.$partner->id, [
            'name' => 'Cloud Platform Partner (renamed)',
            'sort_order' => 7,
        ])->assertOk()->assertJsonPath('data.name', 'Cloud Platform Partner (renamed)');

        $partner->refresh();
        $this->assertSame('Cloud Platform Partner (renamed)', $partner->name);
        $this->assertSame(7, $partner->sort_order);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'partners.update',
            'auditable_type' => Partner::class,
            'auditable_id' => $partner->id,
        ]);
    }

    #[Test]
    public function admin_can_publish_partner_and_published_at_is_set(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $partner = $this->makePartner();

        $this->postJson('/api/admin/partners/'.$partner->id.'/publish')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);

        $partner->refresh();
        $this->assertSame(ContentStatus::Published, $partner->content_status);
        $this->assertNotNull($partner->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'partners.publish',
            'auditable_type' => Partner::class,
            'auditable_id' => $partner->id,
        ]);
    }

    #[Test]
    public function admin_can_hide_and_archive_a_partner(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $hidden = $this->makePartner(['content_status' => ContentStatus::Published, 'published_at' => now()]);
        $this->postJson('/api/admin/partners/'.$hidden->id.'/hide')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Hidden->value);
        $this->assertNotNull($hidden->refresh()->hidden_at);

        $archived = $this->makePartner();
        $this->postJson('/api/admin/partners/'.$archived->id.'/archive')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Archived->value);
        $this->assertNotNull($archived->refresh()->archived_at);
    }

    #[Test]
    public function index_can_filter_by_content_status_and_visibility(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $public = $this->makePartner([
            'name' => 'Public Partner',
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
        ]);
        $hidden = $this->makePartner([
            'name' => 'Hidden Partner',
            'content_status' => ContentStatus::Hidden,
            'visibility' => ContentVisibility::Public,
        ]);
        $membersOnly = $this->makePartner([
            'name' => 'Member Partner',
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::MembersOnly,
        ]);

        $publishedIds = collect($this->getJson('/api/admin/partners?content_status=published')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($public->id, $publishedIds);
        $this->assertContains($membersOnly->id, $publishedIds);
        $this->assertNotContains($hidden->id, $publishedIds);

        $membersOnlyIds = collect($this->getJson('/api/admin/partners?visibility=members_only')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$membersOnly->id], $membersOnlyIds);
    }

    #[Test]
    public function super_admin_can_use_admin_partner_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]));

        $this->getJson('/api/admin/partners')->assertOk();
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
    private function makePartner(array $overrides = []): Partner
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return Partner::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Draft,
            'visibility' => ContentVisibility::Public,
            'name' => 'Cloud Platform Partner',
            'partner_type' => 'Cloud credits',
            'summary' => 'Compute credits and infrastructure support.',
            'description' => 'Partner profile for startup infrastructure and member education support.',
            'website_url' => 'https://example.com/partner',
            'logo_url' => 'https://example.com/partner-logo.png',
            'sort_order' => 0,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Cloud Platform Partner',
            'partner_type' => 'Cloud credits',
            'summary' => 'Compute credits and infrastructure support.',
            'description' => 'Partner profile for startup infrastructure and member education support.',
            'website_url' => 'https://example.com/partner',
            'logo_url' => 'https://example.com/partner-logo.png',
            'visibility' => ContentVisibility::Public->value,
            'sort_order' => 0,
        ], $overrides);
    }
}
