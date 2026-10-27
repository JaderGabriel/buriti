<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jirau_card_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['card_id', 'user_id', 'role']);
            $table->index(['user_id', 'role']);
        });

        $now = now();
        DB::table('jirau_cards')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->get(['id', 'assigned_to'])
            ->each(function (object $card) use ($now): void {
                DB::table('jirau_card_participants')->insert([
                    'card_id' => $card->id,
                    'user_id' => $card->assigned_to,
                    'role' => 'responsible',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('jirau_card_participants');
    }
};
