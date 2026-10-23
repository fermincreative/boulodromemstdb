<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            // Moved to "Te verlengen" by hand for this year, before the membership is within the renewal window.
            $table->unsignedSmallInteger('renew_early_for')->nullable()->after('cancelled_at');
        });

        DB::table('settings')->insert([
            'key' => 'renewal_window_days',
            'value' => json_encode(42),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'renewal_window_days')->delete();

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('renew_early_for');
        });
    }
};
