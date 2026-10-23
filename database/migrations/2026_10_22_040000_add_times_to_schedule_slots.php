<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every part of a day keeps its own times; extra parts of a day ("custom") can be added, so (date, period) is no longer unique.
        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->string('starts_at', 5)->default('00:00')->after('period');
            $table->string('ends_at', 5)->default('00:00')->after('starts_at');
            $table->dropUnique(['date', 'period']);
            $table->index('date');
        });

        DB::table('schedule_slots')->where('period', 'afternoon')->update(['starts_at' => '13:00', 'ends_at' => '17:00']);
        DB::table('schedule_slots')->where('period', 'evening')->update(['starts_at' => '19:00', 'ends_at' => '23:00']);

        $now = now();
        DB::table('settings')->insert([
            ['key' => 'schedule_afternoon_times', 'value' => json_encode(['13:00', '17:00']), 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'schedule_evening_times', 'value' => json_encode(['19:00', '23:00']), 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['schedule_afternoon_times', 'schedule_evening_times'])->delete();
        DB::table('schedule_slots')->where('period', 'custom')->delete();

        Schema::table('schedule_slots', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropColumn(['starts_at', 'ends_at']);
            $table->unique(['date', 'period']);
        });
    }
};
