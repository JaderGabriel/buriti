<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jirau_columns', function (Blueprint $table) {
            $table->unsignedSmallInteger('default_due_hours')->nullable()->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('jirau_columns', function (Blueprint $table) {
            $table->dropColumn('default_due_hours');
        });
    }
};
