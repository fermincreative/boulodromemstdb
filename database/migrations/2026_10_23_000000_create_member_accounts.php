<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A member's login (ledenportaal). One per e-mail address; members sharing an address (couples) share it.
        Schema::create('member_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->rememberToken();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('member_account_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Login links: only a hash of the token is stored; single use, short-lived.
        Schema::create('member_login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_account_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_login_tokens');

        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('member_account_id');
        });

        Schema::dropIfExists('member_accounts');
    }
};
