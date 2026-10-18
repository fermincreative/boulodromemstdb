<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clubs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Clubs known from the old member list. "Geen" is not a club: members without one have club_id = null.
        DB::table('clubs')->insert(collect(['SBM Halpas', 'Mosa Petanca', 'JBC Amby'])
            ->map(fn (string $name) => ['name' => $name, 'created_at' => now(), 'updated_at' => now()])
            ->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('clubs');
    }
};
