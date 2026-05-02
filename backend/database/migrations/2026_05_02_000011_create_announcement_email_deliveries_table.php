<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcement_email_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('announcement_published_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['announcement_id', 'user_id', 'announcement_published_at'],
                'announcement_email_unique_delivery'
            );
            $table->index(['announcement_id', 'sent_at']);
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_email_deliveries');
    }
};
