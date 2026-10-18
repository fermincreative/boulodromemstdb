<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status')->default('pending')->index();
            $table->timestamp('card_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['member_id', 'starts_at']);
            $table->index(['member_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
