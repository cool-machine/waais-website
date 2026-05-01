<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->string('section')->index();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('body');
            $table->string('link_label')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_cards');
    }
};
