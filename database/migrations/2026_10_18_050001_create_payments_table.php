<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One payment can cover several memberships (a December sign-up pays this year's rest and next year at once).
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('description');
            $table->unsignedInteger('amount_cents');
            $table->string('status')->default('open')->index();
            $table->string('method')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::table('memberships', function (Blueprint $table) {
            $table->foreignId('payment_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_id');
        });

        Schema::dropIfExists('payments');
    }
};
