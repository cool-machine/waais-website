<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Public-content lifecycle (shared vocabulary with startup listings).
            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            // Event-specific lifecycle. Cancellation is separate from
            // content_status because a cancelled event must remain
            // visible to admins but hidden from public surfaces.
            $table->timestamp('cancelled_at')->nullable()->index();
            $table->text('cancellation_note')->nullable();

            // Recap content; when set on a past event, the public
            // surface labels the event as recap-ready.
            $table->text('recap_content')->nullable();

            // Reminder timing — admin-configurable, default 2 days
            // before the event. Stored in days for portability across
            // SQLite (dev) and Postgres (production).
            $table->unsignedSmallInteger('reminder_days_before')->default(2);

            // Event content.
            $table->string('title');
            $table->text('summary');
            $table->text('description');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->nullable();
            $table->string('location')->nullable();
            $table->string('format')->nullable();
            $table->string('image_url')->nullable();

            // External registration. Internal RSVP is a later option
            // per PRODUCT.md, so capacity is informational and the
            // waitlist is an admin-toggled flag rather than a derived
            // count.
            $table->string('registration_url')->nullable();
            $table->unsignedInteger('capacity_limit')->nullable();
            $table->boolean('waitlist_open')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
