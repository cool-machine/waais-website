<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Per-area admins may only reach their own area; everything else (including
 * the super-admin-only areas) returns 403. Super admins reach everything.
 */
class AdminAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function events_admin_can_only_reach_events(): void
    {
        Sanctum::actingAs(User::factory()->eventsAdmin()->create());

        $this->getJson('/api/admin/events')->assertOk();

        $this->getJson('/api/admin/partners')->assertForbidden();
        $this->getJson('/api/admin/startup-listings')->assertForbidden();
        $this->getJson('/api/admin/announcements')->assertForbidden();
        $this->getJson('/api/admin/applications')->assertForbidden();
        $this->getJson('/api/admin/homepage-cards')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function partners_admin_can_only_reach_partners(): void
    {
        Sanctum::actingAs(User::factory()->partnersAdmin()->create());

        $this->getJson('/api/admin/partners')->assertOk();

        $this->getJson('/api/admin/events')->assertForbidden();
        $this->getJson('/api/admin/startup-listings')->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    #[Test]
    public function startups_admin_can_only_reach_startups(): void
    {
        Sanctum::actingAs(User::factory()->startupsAdmin()->create());

        $this->getJson('/api/admin/startup-listings')->assertOk();

        $this->getJson('/api/admin/events')->assertForbidden();
        $this->getJson('/api/admin/partners')->assertForbidden();
        $this->getJson('/api/admin/applications')->assertForbidden();
    }

    #[Test]
    public function super_admin_can_reach_every_area(): void
    {
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $this->getJson('/api/admin/events')->assertOk();
        $this->getJson('/api/admin/partners')->assertOk();
        $this->getJson('/api/admin/startup-listings')->assertOk();
        $this->getJson('/api/admin/announcements')->assertOk();
        $this->getJson('/api/admin/applications')->assertOk();
        $this->getJson('/api/admin/homepage-cards')->assertOk();
        $this->getJson('/api/admin/users')->assertOk();
    }

    #[Test]
    public function plain_member_is_forbidden_from_all_admin_areas(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'approval_status' => \App\Enums\ApprovalStatus::Approved,
            'permission_role' => \App\Enums\PermissionRole::Member,
        ]));

        $this->getJson('/api/admin/events')->assertForbidden();
        $this->getJson('/api/admin/partners')->assertForbidden();
        $this->getJson('/api/admin/startup-listings')->assertForbidden();
    }
}
