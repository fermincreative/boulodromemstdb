<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('member_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable()->index();
            $table->string('phone_mobile')->nullable();
            $table->string('phone_home')->nullable();
            $table->string('street')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->date('birthday')->nullable();
            $table->foreignId('club_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_for')->nullable();
            $table->date('joined_at')->nullable();
            $table->date('cancelled_at')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
