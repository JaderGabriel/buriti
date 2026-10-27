<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('totp_secret')->nullable()->after('telegram_chat_id');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
            $table->unsignedBigInteger('totp_last_step')->nullable()->after('totp_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at', 'totp_last_step']);
        });
    }
};
