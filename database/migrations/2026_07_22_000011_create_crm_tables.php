<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->string('preferred_channel', 20)->nullable();
            $table->string('status', 20)->default('lead');
            $table->string('source', 20)->default('manual');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('opportunities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('stage', 20)->default('lead');
            $table->decimal('value', 12, 2)->nullable();
            $table->date('expected_close_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('note');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamp('happened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_id', 'project_id']);
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::table('contact_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
        });

        Schema::dropIfExists('contact_project');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('opportunities');
        Schema::dropIfExists('contacts');
    }
};
