<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jirau_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jirau_id')->constrained('jiraus')->cascadeOnDelete();
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('column_id')->nullable()->constrained('jirau_columns')->nullOnDelete();
            $table->string('column_name', 80);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('seconds')->default(0);
            $table->timestamps();

            $table->index(['card_id', 'ended_at']);
            $table->index(['user_id', 'ended_at']);
            $table->index(['jirau_id', 'card_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jirau_time_entries');
    }
};
