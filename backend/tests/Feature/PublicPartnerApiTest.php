<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicPartnerApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_index_returns_only_published_public_or_mixed_partners(): void
    {
        $publicPartner = $this->makePartner(ContentStatus::Published, ContentVisibility::Public, ['name' => 'Public Partner']);
        $mixedPartner = $this->makePartner(ContentStatus::Published, ContentVisibility::Mixed, ['name' => 'Mixed Partner']);
        $this->makePartner(ContentStatus::Draft, ContentVisibility::Public, ['name' => 'Draft']);
        $this->makePartner(ContentStatus::Hidden, ContentVisibility::Public, ['name' => 'Hidden']);
        $this->makePartner(ContentStatus::Archived, ContentVisibility::Public, ['name' => 'Archived']);
        $this->makePartner(ContentStatus::Published, ContentVisibility::MembersOnly, ['name' => 'Members Only']);

        $response = $this->getJson('/api/public/partners')->assertOk();

        $names = collect($response->json('data'))->pluck('name')->all();
        sort($names);
        $this->assertSame(['Mixed Partner', 'Public Partner'], $names);
        $this->assertContains($publicPartner->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertContains($mixedPartner->id, collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function index_sorts_by_sort_order_then_name(): void
    {
        $third = $this->makePartner(ContentStatus::Published, ContentVisibility::Public, [
            'name' => 'Zeta Partner',
            'sort_order' => 20,
        ]);
        $second = $this->makePartner(ContentStatus::Published, ContentVisibility::Public, [
            'name' => 'Beta Partner',
            'sort_order' => 10,
        ]);
        $first = $this->makePartner(ContentStatus::Published, ContentVisibility::Public, [
            'name' => 'Alpha Partner',
            'sort_order' => 10,
        ]);

        $ids = collect($this->getJson('/api/public/partners')->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$first->id, $second->id, $third->id], $ids);
    }

    #[Test]
    public function show_returns_a_published_public_partner(): void
    {
        $partner = $this->makePartner(ContentStatus::Published, ContentVisibility::Public);

        $this->getJson('/api/public/partners/'.$partner->id)
            ->assertOk()
            ->assertJsonPath('data.id', $partner->id)
            ->assertJsonPath('data.name', 'Cloud Platform Partner');
    }

    #[Test]
    public function show_returns_404_for_non_public_partner(): void
    {
        $draft = $this->makePartner(ContentStatus::Draft, ContentVisibility::Public);
        $hidden = $this->makePartner(ContentStatus::Hidden, ContentVisibility::Public);
        $archived = $this->makePartner(ContentStatus::Archived, ContentVisibility::Public);
        $membersOnly = $this->makePartner(ContentStatus::Published, ContentVisibility::MembersOnly);

        $this->getJson('/api/public/partners/'.$draft->id)->assertNotFound();
        $this->getJson('/api/public/partners/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/public/partners/'.$archived->id)->assertNotFound();
        $this->getJson('/api/public/partners/'.$membersOnly->id)->assertNotFound();
        $this->getJson('/api/public/partners/9999')->assertNotFound();
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        $partner = $this->makePartner(ContentStatus::Published, ContentVisibility::Public);

        $payload = $this->getJson('/api/public/partners/'.$partner->id)->assertOk()->json('data');

        $expected = [
            'id', 'name', 'partner_type', 'summary', 'description',
            'website_url', 'logo_url', 'visibility', 'published_at',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing public field: {$field}");
        }

        $actualKeys = array_keys($payload);
        sort($actualKeys);
        $expectedKeys = $expected;
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Public partners projection key set drifted from the documented allowlist.');

        $forbidden = [
            'created_by', 'creator', 'content_status', 'hidden_at', 'archived_at',
            'sort_order', 'created_at', 'updated_at',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $payload, "Public partners projection leaked: {$field}");
        }
    }

    #[Test]
    public function index_paginates_with_default_per_page_and_validates_limits(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makePartner(ContentStatus::Published, ContentVisibility::Public, [
                'name' => 'Partner '.$i,
                'sort_order' => $i,
            ]);
        }

        $response = $this->getJson('/api/public/partners')->assertOk();

        $this->assertCount(12, $response->json('data'));
        $this->assertSame(15, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));

        $this->getJson('/api/public/partners?per_page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/public/partners?per_page=999')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makePartner(ContentStatus $contentStatus, ContentVisibility $visibility, array $overrides = []): Partner
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return Partner::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => $contentStatus,
            'visibility' => $visibility,
            'published_at' => $contentStatus === ContentStatus::Published ? now() : null,
            'name' => 'Cloud Platform Partner',
            'partner_type' => 'Cloud credits',
            'summary' => 'Compute credits and infrastructure support.',
            'description' => 'Partner profile for startup infrastructure and member education support.',
            'website_url' => 'https://example.com/partner',
            'logo_url' => 'https://example.com/partner-logo.png',
            'sort_order' => 0,
        ], $overrides));
    }
}
