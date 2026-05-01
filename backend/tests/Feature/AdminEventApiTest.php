<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ContentStatus;
use App\Enums\ContentVisibility;
use App\Enums\PermissionRole;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminEventApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pending_user_cannot_access_admin_event_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Submitted,
            'permission_role' => PermissionRole::PendingUser,
        ]));

        $this->getJson('/api/admin/events')->assertForbidden();
    }

    #[Test]
    public function regular_member_cannot_access_admin_event_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/events')->assertForbidden();
    }

    #[Test]
    public function admin_can_create_a_draft_event(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/events', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'AI Founder Salon')
            ->assertJsonPath('data.content_status', ContentStatus::Draft->value)
            ->assertJsonPath('data.visibility', ContentVisibility::MembersOnly->value);

        $event = Event::find($response->json('data.id'));
        $this->assertSame($admin->id, $event->created_by);
        $this->assertNull($event->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'events.create',
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
        ]);
    }

    #[Test]
    public function event_creation_validates_required_fields(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/events', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'summary', 'description', 'starts_at']);
    }

    #[Test]
    public function ends_at_must_be_at_or_after_starts_at(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/events', $this->payload([
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDay()->toIso8601String(),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['ends_at']);
    }

    #[Test]
    public function admin_can_update_an_event_and_changes_are_audited(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $event = $this->makeEvent();

        $this->patchJson('/api/admin/events/'.$event->id, [
            'title' => 'AI Founder Salon (renamed)',
            'capacity_limit' => 75,
        ])->assertOk()->assertJsonPath('data.title', 'AI Founder Salon (renamed)');

        $event->refresh();
        $this->assertSame('AI Founder Salon (renamed)', $event->title);
        $this->assertSame(75, $event->capacity_limit);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'events.update',
            'auditable_type' => Event::class,
            'auditable_id' => $event->id,
        ]);
    }

    #[Test]
    public function admin_can_publish_event_and_published_at_is_set(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $event = $this->makeEvent();
        $this->assertSame(ContentStatus::Draft, $event->content_status);
        $this->assertNull($event->published_at);

        $this->postJson('/api/admin/events/'.$event->id.'/publish')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Published->value);

        $event->refresh();
        $this->assertSame(ContentStatus::Published, $event->content_status);
        $this->assertNotNull($event->published_at);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'events.publish',
        ]);
    }

    #[Test]
    public function admin_can_hide_a_published_event(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $event = $this->makeEvent(['content_status' => ContentStatus::Published, 'published_at' => now()]);

        $this->postJson('/api/admin/events/'.$event->id.'/hide')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Hidden->value);

        $event->refresh();
        $this->assertSame(ContentStatus::Hidden, $event->content_status);
        $this->assertNotNull($event->hidden_at);
    }

    #[Test]
    public function admin_can_archive_an_event(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent();

        $this->postJson('/api/admin/events/'.$event->id.'/archive')->assertOk()
            ->assertJsonPath('data.content_status', ContentStatus::Archived->value);

        $event->refresh();
        $this->assertNotNull($event->archived_at);
    }

    #[Test]
    public function admin_can_cancel_an_event_with_optional_note(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $event = $this->makeEvent(['content_status' => ContentStatus::Published, 'published_at' => now()]);

        $this->postJson('/api/admin/events/'.$event->id.'/cancel', [
            'cancellation_note' => 'Speaker cancelled.',
        ])->assertOk();

        $event->refresh();
        $this->assertNotNull($event->cancelled_at);
        $this->assertSame('Speaker cancelled.', $event->cancellation_note);
        // content_status is unchanged on cancellation; cancellation is
        // a separate axis from the content lifecycle.
        $this->assertSame(ContentStatus::Published, $event->content_status);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'events.cancel',
        ]);
    }

    #[Test]
    public function cancelling_an_already_cancelled_event_returns_409(): void
    {
        Sanctum::actingAs($this->makeAdmin());
        $event = $this->makeEvent(['cancelled_at' => now()->subDay()]);

        $this->postJson('/api/admin/events/'.$event->id.'/cancel')->assertStatus(409);
    }

    #[Test]
    public function index_can_filter_by_content_status_and_time_window(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $upcoming = $this->makeEvent(['starts_at' => now()->addDays(3)]);
        $past = $this->makeEvent(['starts_at' => now()->subDays(3)]);
        $hidden = $this->makeEvent(['content_status' => ContentStatus::Hidden, 'starts_at' => now()->addDays(2)]);

        $upcomingIds = collect($this->getJson('/api/admin/events?time=upcoming')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertContains($upcoming->id, $upcomingIds);
        $this->assertContains($hidden->id, $upcomingIds);
        $this->assertNotContains($past->id, $upcomingIds);

        $hiddenIds = collect($this->getJson('/api/admin/events?content_status=hidden')->assertOk()->json('data'))->pluck('id')->all();
        $this->assertSame([$hidden->id], $hiddenIds);
    }

    #[Test]
    public function super_admin_can_use_admin_event_routes(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::SuperAdmin,
        ]));

        $this->getJson('/api/admin/events')->assertOk();
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
    private function makeEvent(array $overrides = []): Event
    {
        $admin = User::factory()->create([
            'approval_status' => ApprovalStatus::Approved,
            'permission_role' => PermissionRole::Admin,
        ]);

        return Event::create(array_replace([
            'created_by' => $admin->id,
            'content_status' => ContentStatus::Draft,
            'visibility' => ContentVisibility::MembersOnly,
            'title' => 'AI Founder Salon',
            'summary' => 'A focused salon for alumni founders building AI companies.',
            'description' => 'Invitation-only dinner for alumni founders, operators, and investors.',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(3),
            'location' => 'New York',
            'format' => 'Private dinner',
            'capacity_limit' => 50,
            'reminder_days_before' => 2,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'AI Founder Salon',
            'summary' => 'A focused salon for alumni founders building AI companies.',
            'description' => 'Invitation-only dinner for alumni founders, operators, and investors.',
            'starts_at' => now()->addDays(7)->toIso8601String(),
            'ends_at' => now()->addDays(7)->addHours(3)->toIso8601String(),
            'location' => 'New York',
            'format' => 'Private dinner',
            'image_url' => 'https://example.com/event.jpg',
            'registration_url' => 'https://example.com/register',
            'capacity_limit' => 50,
            'visibility' => ContentVisibility::MembersOnly->value,
            'reminder_days_before' => 2,
        ], $overrides);
    }
}
