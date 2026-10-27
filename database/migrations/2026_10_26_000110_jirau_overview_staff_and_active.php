<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('template');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('jirau_role', 20)->nullable()->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('jirau_role');
        });
    }
};
