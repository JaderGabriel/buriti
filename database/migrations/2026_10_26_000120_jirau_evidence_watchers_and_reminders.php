<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jirau_card_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('url', 500);
            $table->string('kind', 20);
            $table->string('title', 160)->nullable();
            $table->timestamps();
        });

        Schema::create('jirau_column_user', function (Blueprint $table) {
            $table->foreignId('column_id')->constrained('jirau_columns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['column_id', 'user_id']);
        });

        Schema::table('jirau_cards', function (Blueprint $table) {
            $table->timestamp('telegram_reminder_sent_at')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('jirau_cards', function (Blueprint $table) {
            $table->dropColumn('telegram_reminder_sent_at');
        });
        Schema::dropIfExists('jirau_column_user');
        Schema::dropIfExists('jirau_card_links');
    }
};
