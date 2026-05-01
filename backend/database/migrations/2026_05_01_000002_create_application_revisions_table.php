<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changed_fields');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('change_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_revisions');
    }
};
