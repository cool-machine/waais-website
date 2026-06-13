<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\HomepageCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminHomepageCardApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_admin_homepage_card_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/homepage-cards')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_admin_homepage_card_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/homepage-cards')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_a_draft_homepage_card(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/homepage-cards', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Events with memory')
            ->assertJsonPath('data.content_status', ContentStatus::Draft->value)
            ->assertJsonPath('data.visibility', ContentVisibility::Public->value);

        $card = HomepageCard::find($response->json('data.id'));
        $this->assertSame($admin->id, $card->created_by);
        $this->assertNull($card->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'homepage_cards.create',
            'auditable_type' => HomepageCard::class,
            'auditable_id' => $card->id,
        ]);
    }

    #[Test]
    public function homepage_card_creation_validates_required_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/homepage-cards', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section', 'title', 'body']);
    }

    #[Test]
    public function admin_can_update_a_homepage_card_and_changes_are_audited(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $card = $this->makeCard();

        $this->patchJson('/api/admin/homepage-cards/'.$card->id, [
            'title' => 'Events with durable memory',
            'sort_order' => 9,
        ])->assertOk()->assertJsonPath('data.title', 'Events with durable memory');

        $card->refresh();
        $this->assertSame('Events with durable memory', $card->title);
        $this->assertSame(9, $card->sort_order);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'homepage_cards.update',
            'auditable_type' => HomepageCard::class,
            'auditable_id' => $card->id,
        ]);
    }

    #[Test]
    public function admin_can_publish_hide_and_archive_homepage_cards(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $published = $this->makeCard();
        $this->postJson('/api/admin/homepage-cards/'.$published->id.'/publish')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);
        $this->assertNotNull($published->refresh()->published_at);

        $hidden = $this->makeCard(['content_status' => ContentStatus::Published, 'published_at' => now()]);
        $this->postJson('/api/admin/homepage-cards/'.$hidden->id.'/hide')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Hidden->value);
        $this->assertNotNull($hidden->refresh()->hidden_at);

        $archived = $this->makeCard();
        $this->postJson('/api/admin/homepage-cards/'.$archived->id.'/archive')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Archived->value);
        $this->assertNotNull($archived->refresh()->archived_at);
    }

    #[Test]
    public function index_can_filter_by_section_status_and_visibility(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $whatWeDo = $this->makeCard([
            'section' => 'what_we_do',
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
        ]);
        $accessFlow = $this->makeCard([
            'section' => 'access_flow',
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::Public,
        ]);
        $membersOnly = $this->makeCard([
            'section' => 'what_we_do',
            'content_status' => ContentStatus::Published,
            'visibility' => ContentVisibility::MembersOnly,
        ]);

        $sectionIds = collect($this->getJson('/api/admin/homepage-cards?section=what_we_do')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($whatWeDo->id, $sectionIds);
        $this->assertContains($membersOnly->id, $sectionIds);
        $this->assertNotContains($accessFlow->id, $sectionIds);

        $membersOnlyIds = collect($this->getJson('/api/admin/homepage-cards?visibility=members_only')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$membersOnly->id], $membersOnlyIds);
    }

    #[Test]
    public function super_admin_can_use_admin_homepage_card_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]));

        $this->getJson('/api/admin/homepage-cards')->assertOk();
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
    private function makeCard(array $overrides = []): HomepageCard
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return HomepageCard::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Draft,
            'visibility' => ContentVisibility::Public,
            'section' => 'what_we_do',
            'eyebrow' => 'Programs',
            'title' => 'Events with memory',
            'body' => 'Host salons, roundtables, workshops, startup demo nights, and recap pages.',
            'link_label' => 'Explore events',
            'link_url' => '/events',
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
            'section' => 'what_we_do',
            'eyebrow' => 'Programs',
            'title' => 'Events with memory',
            'body' => 'Host salons, roundtables, workshops, startup demo nights, and recap pages.',
            'link_label' => 'Explore events',
            'link_url' => '/events',
            'visibility' => ContentVisibility::Public->value,
            'sort_order' => 0,
        ], $overrides);
    }
}
