<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();

            // Public-content lifecycle shared with events and homepage CMS.
            $table->string('content_status')->default('draft')->index();
            $table->string('visibility')->default('public')->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('archived_at')->nullable();

            $table->string('name');
            $table->string('partner_type')->nullable();
            $table->text('summary');
            $table->text('description');
            $table->string('website_url')->nullable();
            $table->string('logo_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
