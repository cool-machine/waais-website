<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('startup_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();

            // Review workflow (mirrors membership applications).
            $table->string('approval_status')->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Public-content lifecycle.
            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();

            // Listing content (lean v1).
            $table->string('name');
            $table->string('tagline');
            $table->text('description');
            $table->string('website_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('industry');
            $table->string('stage')->nullable();
            $table->string('location')->nullable();
            $table->json('founders')->nullable();
            $table->string('submitter_role')->nullable();
            $table->string('linkedin_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('startup_listings');
    }
};
