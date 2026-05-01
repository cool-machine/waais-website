<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\HomepageCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicHomepageCardApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_index_returns_only_published_public_or_mixed_cards(): void
    {
        $publicCard = $this->makeCard(ContentStatus::Published, ContentVisibility::Public, ['title' => 'Public Card']);
        $mixedCard = $this->makeCard(ContentStatus::Published, ContentVisibility::Mixed, ['title' => 'Mixed Card']);
        $this->makeCard(ContentStatus::Draft, ContentVisibility::Public, ['title' => 'Draft']);
        $this->makeCard(ContentStatus::Hidden, ContentVisibility::Public, ['title' => 'Hidden']);
        $this->makeCard(ContentStatus::Archived, ContentVisibility::Public, ['title' => 'Archived']);
        $this->makeCard(ContentStatus::Published, ContentVisibility::MembersOnly, ['title' => 'Members Only']);

        $response = $this->getJson('/api/public/homepage-cards')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        sort($titles);
        $this->assertSame(['Mixed Card', 'Public Card'], $titles);
        $this->assertContains($publicCard->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertContains($mixedCard->id, collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function index_can_filter_by_section_and_sorts_by_section_then_sort_order(): void
    {
        $third = $this->makeCard(ContentStatus::Published, ContentVisibility::Public, [
            'section' => 'what_we_do',
            'title' => 'Third',
            'sort_order' => 20,
        ]);
        $second = $this->makeCard(ContentStatus::Published, ContentVisibility::Public, [
            'section' => 'what_we_do',
            'title' => 'Second',
            'sort_order' => 10,
        ]);
        $first = $this->makeCard(ContentStatus::Published, ContentVisibility::Public, [
            'section' => 'access_flow',
            'title' => 'First',
            'sort_order' => 10,
        ]);

        $allIds = collect($this->getJson('/api/public/homepage-cards')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$first->id, $second->id, $third->id], $allIds);

        $sectionIds = collect($this->getJson('/api/public/homepage-cards?section=what_we_do')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$second->id, $third->id], $sectionIds);
    }

    #[Test]
    public function show_returns_a_published_public_card(): void
    {
        $card = $this->makeCard(ContentStatus::Published, ContentVisibility::Public);

        $this->getJson('/api/public/homepage-cards/'.$card->id)
            ->assertOk()
            ->assertJsonPath('data.id', $card->id)
            ->assertJsonPath('data.title', 'Events with memory');
    }

    #[Test]
    public function show_returns_404_for_non_public_card(): void
    {
        $draft = $this->makeCard(ContentStatus::Draft, ContentVisibility::Public);
        $hidden = $this->makeCard(ContentStatus::Hidden, ContentVisibility::Public);
        $archived = $this->makeCard(ContentStatus::Archived, ContentVisibility::Public);
        $membersOnly = $this->makeCard(ContentStatus::Published, ContentVisibility::MembersOnly);

        $this->getJson('/api/public/homepage-cards/'.$draft->id)->assertNotFound();
        $this->getJson('/api/public/homepage-cards/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/public/homepage-cards/'.$archived->id)->assertNotFound();
        $this->getJson('/api/public/homepage-cards/'.$membersOnly->id)->assertNotFound();
        $this->getJson('/api/public/homepage-cards/9999')->assertNotFound();
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        $card = $this->makeCard(ContentStatus::Published, ContentVisibility::Public);

        $payload = $this->getJson('/api/public/homepage-cards/'.$card->id)->assertOk()->json('data');

        $expected = [
            'id', 'section', 'eyebrow', 'title', 'body',
            'link_label', 'link_url', 'visibility', 'published_at',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing public field: {$field}");
        }

        $actualKeys = array_keys($payload);
        sort($actualKeys);
        $expectedKeys = $expected;
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Public homepage-card projection key set drifted from the documented allowlist.');

        $forbidden = [
            'created_by', 'creator', 'content_status', 'hidden_at', 'archived_at',
            'sort_order', 'created_at', 'updated_at',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $payload, "Public homepage-card projection leaked: {$field}");
        }
    }

    #[Test]
    public function index_paginates_and_validates_limits(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->makeCard(ContentStatus::Published, ContentVisibility::Public, [
                'title' => 'Card '.$i,
                'sort_order' => $i,
            ]);
        }

        $response = $this->getJson('/api/public/homepage-cards')->assertOk();

        $this->assertCount(48, $response->json('data'));
        $this->assertSame(50, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));

        $this->getJson('/api/public/homepage-cards?per_page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/public/homepage-cards?per_page=999')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeCard(ContentStatus $contentStatus, ContentVisibility $visibility, array $overrides = []): HomepageCard
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return HomepageCard::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => $contentStatus,
            'visibility' => $visibility,
            'published_at' => $contentStatus === ContentStatus::Published ? now() : null,
            'section' => 'what_we_do',
            'eyebrow' => 'Programs',
            'title' => 'Events with memory',
            'body' => 'Host salons, roundtables, workshops, startup demo nights, and recap pages.',
            'link_label' => 'Explore events',
            'link_url' => '/events',
            'sort_order' => 0,
        ], $overrides));
    }
}
