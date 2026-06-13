<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-area admin permissions. An admin (permission_role = admin) may be
 * scoped to any combination of these areas; super admins implicitly have
 * all of them. Approvals, announcements, homepage cards, and role
 * assignment remain super-admin only and need no flag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('can_manage_events')->default(false)->after('permission_role');
            $table->boolean('can_manage_partners')->default(false)->after('can_manage_events');
            $table->boolean('can_manage_startups')->default(false)->after('can_manage_partners');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['can_manage_events', 'can_manage_partners', 'can_manage_startups']);
        });
    }
};
