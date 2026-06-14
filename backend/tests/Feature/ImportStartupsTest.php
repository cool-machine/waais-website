<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\StartupListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportStartupsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_startups_as_approved_published_public_owned_by_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-startups', ['path' => $path])->assertSuccessful();

        $listing = StartupListing::query()->where('website_url', 'https://example-startup.com/')->first();
        $this->assertNotNull($listing);
        $this->assertSame('Example Startup', $listing->name);
        $this->assertSame(ApprovalStatus::Approved, $listing->approval_status);
        $this->assertSame(ContentStatus::Published, $listing->content_status);
        $this->assertSame(ContentVisibility::Public, $listing->visibility);
        $this->assertSame($admin->id, $listing->owner_id);
        $this->assertNotNull($listing->approved_at);
        $this->assertSame(['Jane Founder'], $listing->founders);

        @unlink($path);
    }

    #[Test]
    public function rerun_is_create_only_and_update_overwrites(): void
    {
        User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-startups', ['path' => $path])->assertSuccessful();
        $listing = StartupListing::query()->where('website_url', 'https://example-startup.com/')->firstOrFail();
        $listing->update(['name' => 'Admin edited name']);

        // Default re-run skips existing (keyed on website_url).
        $this->artisan('waais:import-startups', ['path' => $path])->assertSuccessful();
        $this->assertSame('Admin edited name', $listing->fresh()->name);
        $this->assertSame(1, StartupListing::query()->where('website_url', 'https://example-startup.com/')->count());

        // --update intentionally overwrites.
        $this->artisan('waais:import-startups', ['path' => $path, '--update' => true])->assertSuccessful();
        $this->assertSame('Example Startup', $listing->fresh()->name);

        @unlink($path);
    }

    #[Test]
    public function the_committed_seed_file_is_valid(): void
    {
        $path = database_path('data/startups.json');
        $this->assertFileExists($path);

        $rows = json_decode((string) file_get_contents($path), true);
        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotEmpty($row['name'] ?? null);
            $this->assertNotEmpty($row['description'] ?? null);
            $this->assertNotEmpty($row['website_url'] ?? null);
        }
    }

    private function writeFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'startup').'.json';
        file_put_contents($path, json_encode([[
            'name' => 'Example Startup',
            'tagline' => 'Doing AI things.',
            'description' => 'A full description of the example startup.',
            'website_url' => 'https://example-startup.com/',
            'logo_url' => 'https://example-startup.com/logo.png',
            'industry' => 'AI',
            'location' => 'Remote',
            'founders' => ['Jane Founder'],
            'submitter_role' => 'Founder & CEO',
            'linkedin_url' => 'https://www.linkedin.com/company/example-startup',
            'visibility' => 'public',
        ]]));

        return $path;
    }
}
