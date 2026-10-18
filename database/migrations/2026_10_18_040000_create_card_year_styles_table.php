<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_year_styles', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->string('badge_color', 7);
            $table->string('text_color', 7);
            $table->timestamps();
        });

        // 2027 gets white on black so it is instantly told apart from 2026 (beige).
        DB::table('card_year_styles')->insert([
            'year' => 2027,
            'badge_color' => '#000000',
            'text_color' => '#FFFFFF',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('card_year_styles');
    }
};
