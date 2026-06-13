<?php

namespace Tests\Feature;

use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImportEventsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_events_as_published_public_owned_by_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-events', ['path' => $path])->assertSuccessful();

        $event = Event::query()->where('external_ref', 'evt-1')->first();
        $this->assertNotNull($event);
        $this->assertSame('Legacy AI Salon', $event->title);
        $this->assertSame(ContentStatus::Published, $event->content_status);
        $this->assertSame(ContentVisibility::Public, $event->visibility);
        $this->assertSame($admin->id, $event->created_by);
        $this->assertNotNull($event->published_at);

        @unlink($path);
    }

    #[Test]
    public function rerun_is_create_only_and_does_not_clobber_admin_edits(): void
    {
        User::factory()->superAdmin()->create();
        $path = $this->writeFixture();

        $this->artisan('waais:import-events', ['path' => $path])->assertSuccessful();
        $event = Event::query()->where('external_ref', 'evt-1')->firstOrFail();
        $event->update(['title' => 'Admin edited title']);

        // Default re-run skips existing.
        $this->artisan('waais:import-events', ['path' => $path])->assertSuccessful();
        $this->assertSame('Admin edited title', $event->fresh()->title);
        $this->assertSame(1, Event::query()->where('external_ref', 'evt-1')->count());

        // --update intentionally overwrites.
        $this->artisan('waais:import-events', ['path' => $path, '--update' => true])->assertSuccessful();
        $this->assertSame('Legacy AI Salon', $event->fresh()->title);

        @unlink($path);
    }

    private function writeFixture(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'evt').'.json';
        file_put_contents($path, json_encode([[
            'external_ref' => 'evt-1',
            'title' => 'Legacy AI Salon',
            'summary' => 'A past salon.',
            'description' => 'Full description of the event.',
            'starts_at' => '2021-05-19T18:00:00+01:00',
            'ends_at' => '2021-05-19T20:00:00+01:00',
            'location' => 'Online',
            'registration_url' => 'https://example.com/evt-1',
            'visibility' => 'public',
        ]]));

        return $path;
    }
}
