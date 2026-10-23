<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Rooster: one row per day and part of the day the Boulodrôme is open. No row = closed.
        Schema::create('schedule_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('period');
            $table->unsignedTinyInteger('lanes');
            $table->string('event_name')->nullable();
            $table->unsignedTinyInteger('event_lanes')->nullable();
            $table->boolean('remaining_lanes_reservable')->default(true);
            $table->timestamps();

            $table->unique(['date', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_slots');
    }
};
