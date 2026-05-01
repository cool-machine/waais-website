<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('users')->cascadeOnDelete();
            $table->string('approval_status')->default('draft')->index();
            $table->string('affiliation_type')->nullable()->index();
            $table->string('email');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_whatsapp')->nullable();
            $table->boolean('is_alumnus');
            $table->string('school_affiliation')->nullable();
            $table->unsignedSmallInteger('graduation_year')->nullable();
            $table->string('inviter_name')->nullable();
            $table->string('primary_location')->nullable();
            $table->string('secondary_location')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('experience_summary')->nullable();
            $table->text('expertise_summary')->nullable();
            $table->json('industries_to_add_value')->nullable();
            $table->json('industries_to_extend_expertise')->nullable();
            $table->string('availability')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_applications');
    }
};
