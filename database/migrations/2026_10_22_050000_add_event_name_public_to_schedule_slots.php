<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            // Whether others (members, the public reservation pages later) may see the event's name; otherwise just "Evenement".
            $table->boolean('event_name_public')->default(false)->after('event_name');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropColumn('event_name_public');
        });
    }
};
