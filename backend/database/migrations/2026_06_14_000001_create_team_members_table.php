<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Public-content lifecycle shared with events / partners CMS.
            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->string('name');
            $table->string('role_title')->nullable();
            // 'founder' or 'advisor' — drives grouping on the About page.
            $table->string('member_group')->default('advisor')->index();
            $table->text('bio')->nullable();
            $table->string('photo_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
