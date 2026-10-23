<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            // Repeated events: every occurrence is its own row; rows of one series share this id.
            $table->uuid('series_id')->nullable()->after('remaining_lanes_reservable')->index();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropIndex(['series_id']);
            $table->dropColumn('series_id');
        });
    }
};
