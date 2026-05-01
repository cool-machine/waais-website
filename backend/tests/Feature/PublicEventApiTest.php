<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEventApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymous_index_returns_only_published_public_or_mixed_events(): void
    {
        $publicEvent = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, ['title' => 'Public Event']);
        $mixedEvent = $this->makeEvent(ContentStatus::Published, ContentVisibility::Mixed, ['title' => 'Mixed Event']);
        $this->makeEvent(ContentStatus::Draft, ContentVisibility::Public, ['title' => 'Draft']);
        $this->makeEvent(ContentStatus::Hidden, ContentVisibility::Public, ['title' => 'Hidden']);
        $this->makeEvent(ContentStatus::Archived, ContentVisibility::Public, ['title' => 'Archived']);
        $this->makeEvent(ContentStatus::Published, ContentVisibility::MembersOnly, ['title' => 'Members Only']);

        $response = $this->getJson('/api/public/events?time=all')->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();
        sort($titles);
        $this->assertSame(['Mixed Event', 'Public Event'], $titles);
        $this->assertContains($publicEvent->id, collect($response->json('data'))->pluck('id')->all());
        $this->assertContains($mixedEvent->id, collect($response->json('data'))->pluck('id')->all());
    }

    #[Test]
    public function cancelled_events_are_invisible_to_anonymous_callers(): void
    {
        $cancelled = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Cancelled Salon',
            'cancelled_at' => now()->subHours(2),
        ]);

        $this->getJson('/api/public/events?time=all')
            ->assertOk()
            ->assertJsonMissing(['title' => 'Cancelled Salon']);

        $this->getJson('/api/public/events/'.$cancelled->id)->assertNotFound();
    }

    #[Test]
    public function default_time_window_is_upcoming_and_excludes_past_events(): void
    {
        $upcoming = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Upcoming',
            'starts_at' => now()->addDays(3),
        ]);
        $past = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Past',
            'starts_at' => now()->subDays(3),
        ]);

        $response = $this->getJson('/api/public/events')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($upcoming->id, $ids);
        $this->assertNotContains($past->id, $ids);
    }

    #[Test]
    public function past_time_window_returns_only_past_events_descending(): void
    {
        $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Future', 'starts_at' => now()->addDays(2),
        ]);
        $oldPast = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Older Past', 'starts_at' => now()->subDays(10),
        ]);
        $recentPast = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'title' => 'Recent Past', 'starts_at' => now()->subDays(2),
        ]);

        $response = $this->getJson('/api/public/events?time=past')->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertSame([$recentPast->id, $oldPast->id], $ids, 'Past events should be sorted descending by starts_at.');
    }

    #[Test]
    public function show_returns_a_published_public_event(): void
    {
        $event = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public);

        $this->getJson('/api/public/events/'.$event->id)
            ->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.title', 'AI Founder Salon');
    }

    #[Test]
    public function show_returns_404_for_non_published_event(): void
    {
        $draft = $this->makeEvent(ContentStatus::Draft, ContentVisibility::Public);
        $hidden = $this->makeEvent(ContentStatus::Hidden, ContentVisibility::Public);
        $archived = $this->makeEvent(ContentStatus::Archived, ContentVisibility::Public);
        $membersOnly = $this->makeEvent(ContentStatus::Published, ContentVisibility::MembersOnly);

        $this->getJson('/api/public/events/'.$draft->id)->assertNotFound();
        $this->getJson('/api/public/events/'.$hidden->id)->assertNotFound();
        $this->getJson('/api/public/events/'.$archived->id)->assertNotFound();
        $this->getJson('/api/public/events/'.$membersOnly->id)->assertNotFound();
        $this->getJson('/api/public/events/9999')->assertNotFound();
    }

    #[Test]
    public function derived_status_reflects_recap_past_and_upcoming(): void
    {
        $upcoming = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'starts_at' => now()->addDays(2),
        ]);
        $past = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'starts_at' => now()->subDays(2),
            'recap_content' => null,
        ]);
        $recap = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'starts_at' => now()->subDays(2),
            'recap_content' => 'A great panel.',
        ]);

        $this->assertSame('upcoming', $this->getJson('/api/public/events/'.$upcoming->id)->json('data.status'));
        $this->assertSame('past', $this->getJson('/api/public/events/'.$past->id)->json('data.status'));
        $this->assertSame('recap', $this->getJson('/api/public/events/'.$recap->id)->json('data.status'));
    }

    #[Test]
    public function projection_excludes_internal_fields(): void
    {
        $event = $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
            'cancellation_note' => 'Internal note — should never leak',
            'reminder_days_before' => 5,
        ]);

        $payload = $this->getJson('/api/public/events/'.$event->id)->assertOk()->json('data');

        // Allowlist — exact set of fields callers can rely on.
        $expected = [
            'id', 'title', 'summary', 'description', 'starts_at', 'ends_at',
            'location', 'format', 'image_url', 'registration_url',
            'capacity_limit', 'waitlist_open', 'visibility', 'recap_content',
            'status', 'published_at',
        ];

        foreach ($expected as $field) {
            $this->assertArrayHasKey($field, $payload, "Missing public field: {$field}");
        }

        $actualKeys = array_keys($payload);
        sort($actualKeys);
        $expectedKeys = $expected;
        sort($expectedKeys);
        $this->assertSame($expectedKeys, $actualKeys, 'Public events projection key set drifted from the documented allowlist.');

        // Denylist — internal / lifecycle / admin-only fields must never appear.
        $forbidden = [
            'created_by', 'creator', 'content_status', 'cancelled_at', 'cancellation_note',
            'reminder_days_before', 'hidden_at', 'archived_at', 'created_at', 'updated_at',
        ];

        foreach ($forbidden as $field) {
            $this->assertArrayNotHasKey($field, $payload, "Public events projection leaked: {$field}");
        }
    }

    #[Test]
    public function index_paginates_with_default_per_page_and_validates_limits(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $this->makeEvent(ContentStatus::Published, ContentVisibility::Public, [
                'title' => 'Event '.$i,
                'starts_at' => now()->addDays($i + 1),
            ]);
        }

        $response = $this->getJson('/api/public/events?time=upcoming')->assertOk();

        $this->assertCount(12, $response->json('data'));
        $this->assertSame(15, $response->json('total'));
        $this->assertSame(2, $response->json('last_page'));

        $this->getJson('/api/public/events?per_page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/public/events?per_page=999')->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeEvent(ContentStatus $contentStatus, ContentVisibility $visibility, array $overrides = []): Event
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return Event::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => $contentStatus,
            'visibility' => $visibility,
            'published_at' => $contentStatus === ContentStatus::Published ? now() : null,
            'title' => 'AI Founder Salon',
            'summary' => 'A focused salon for alumni founders building AI companies.',
            'description' => 'Invitation-only dinner for alumni founders, operators, and investors.',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(3),
            'location' => 'New York',
            'format' => 'Private dinner',
            'image_url' => 'https://example.com/event.jpg',
            'registration_url' => 'https://example.com/register',
            'capacity_limit' => 50,
            'waitlist_open' => false,
            'reminder_days_before' => 2,
        ], $overrides));
    }
}
