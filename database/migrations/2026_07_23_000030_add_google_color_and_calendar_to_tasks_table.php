<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('google_color_id', 2)->nullable()->after('want_meet');
            $table->string('google_calendar_id')->nullable()->after('google_color_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['google_color_id', 'google_calendar_id']);
        });
    }
};
