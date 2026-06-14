<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportPartnersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_partners_as_published_public_owned_by_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-partners', ['path' => $path])->assertSuccessful();

        $partner = Partner::query()->where('website_url', 'https://example-partner.org/')->first();
        $this->assertNotNull($partner);
        $this->assertSame('Example Partner', $partner->name);
        $this->assertSame(ContentStatus::Published, $partner->content_status);
        $this->assertSame(ContentVisibility::Public, $partner->visibility);
        $this->assertSame($admin->id, $partner->created_by);
        $this->assertNotNull($partner->published_at);
        $this->assertSame(7, $partner->sort_order);

        @unlink($path);
    }

    #[Test]
    public function rerun_is_create_only_and_update_overwrites(): void
    {
        User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-partners', ['path' => $path])->assertSuccessful();
        $partner = Partner::query()->where('website_url', 'https://example-partner.org/')->firstOrFail();
        $partner->update(['name' => 'Admin edited name']);

        // Default re-run skips existing (keyed on website_url).
        $this->artisan('waais:import-partners', ['path' => $path])->assertSuccessful();
        $this->assertSame('Admin edited name', $partner->fresh()->name);
        $this->assertSame(1, Partner::query()->where('website_url', 'https://example-partner.org/')->count());

        // --update intentionally overwrites.
        $this->artisan('waais:import-partners', ['path' => $path, '--update' => true])->assertSuccessful();
        $this->assertSame('Example Partner', $partner->fresh()->name);

        @unlink($path);
    }

    #[Test]
    public function the_committed_seed_file_is_valid(): void
    {
        $path = database_path('data/partners.json');
        $this->assertFileExists($path);

        $rows = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['name'] ?? null);
            $this->assertNotEmpty($row['website_url'] ?? null);
        }
    }

    private function writeFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'partner').'.json';
        file_put_contents($path, json_encode([[
            'name' => 'Example Partner',
            'partner_type' => 'Foundation',
            'summary' => 'A partner summary.',
            'description' => 'A full description of the example partner.',
            'website_url' => 'https://example-partner.org/',
            'logo_url' => 'https://example-partner.org/logo.png',
            'sort_order' => 7,
            'visibility' => 'public',
        ]]));

        return $path;
    }
}
