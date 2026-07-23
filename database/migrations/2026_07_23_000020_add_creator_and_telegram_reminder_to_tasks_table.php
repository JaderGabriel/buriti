<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('contact_id')
                ->constrained()
                ->nullOnDelete();
            $table->timestamp('telegram_reminder_sent_at')
                ->nullable()
                ->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('telegram_reminder_sent_at');
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
