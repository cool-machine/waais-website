<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->timestamp('privacy_acknowledged_at')->nullable()->after('age');
            $table->string('privacy_acknowledgement_version')->nullable()->after('privacy_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropColumn(['privacy_acknowledged_at', 'privacy_acknowledgement_version']);
        });
    }
};
