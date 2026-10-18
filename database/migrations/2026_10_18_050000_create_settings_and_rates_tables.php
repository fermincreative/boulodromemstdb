<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        // Joining rates for new members, by the date in the year they sign up (MM-DD).
        Schema::create('joining_rates', function (Blueprint $table) {
            $table->id();
            $table->string('starts_on', 5)->unique();
            $table->unsignedInteger('amount_cents');
            $table->boolean('includes_next_year')->default(false);
            $table->timestamps();
        });

        $now = now();

        DB::table('settings')->insert([
            'key' => 'annual_fee_cents',
            'value' => json_encode(9000),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('joining_rates')->insert(collect([
            ['01-01', 9000, false],
            ['04-01', 6750, false],
            ['07-01', 4500, false],
            ['10-01', 2250, false],
            ['12-01', 1000, true],
        ])->map(fn (array $rate) => [
            'starts_on' => $rate[0],
            'amount_cents' => $rate[1],
            'includes_next_year' => $rate[2],
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('joining_rates');
        Schema::dropIfExists('settings');
    }
};
