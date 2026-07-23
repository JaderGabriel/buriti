<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idea_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('color', 20)->default('amber');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sort_order', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_notes');
    }
};
