<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });

        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropUnique(['project_id']);
        });

        Schema::table('jiraus', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index('project_id');
            $table->string('template', 32)->default('development')->after('name');
        });

        Schema::table('jirau_cards', function (Blueprint $table) {
            $table->foreignId('project_step_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('project_steps')
                ->nullOnDelete();
            $table->unique('project_step_id');
        });
    }

    public function down(): void
    {
        Schema::table('jirau_cards', function (Blueprint $table) {
            $table->dropUnique(['project_step_id']);
            $table->dropConstrainedForeignId('project_step_id');
        });

        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('template');
        });

        Schema::table('jiraus', function (Blueprint $table) {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->unique('project_id');
        });
    }
};
