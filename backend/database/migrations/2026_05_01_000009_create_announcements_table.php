<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Public-content lifecycle. Announcements are admin-authored;
            // publication controls whether members can see them.
            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('members_only')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            // v1 supports dashboard delivery. Email dispatch can be layered
            // onto this same record after delivery policy is finalized.
            $table->string('audience')->default('all_members')->index();
            $table->string('channel')->default('dashboard');

            $table->string('title');
            $table->text('summary')->nullable();
            $table->text('body');
            $table->string('action_label')->nullable();
            $table->string('action_url')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
