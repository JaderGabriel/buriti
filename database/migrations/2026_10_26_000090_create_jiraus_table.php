<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jiraus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jirau_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jirau_id')->constrained('jiraus')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedTinyInteger('wip_limit')->nullable();
            $table->boolean('is_inbox')->default(false);
            $table->boolean('is_done')->default(false);
            $table->string('color', 16)->nullable();
            $table->timestamps();

            $table->unique(['jirau_id', 'slug']);
        });

        Schema::create('jirau_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jirau_id')->constrained('jiraus')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 16);
            $table->timestamps();
        });

        Schema::create('jirau_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jirau_id')->constrained('jiraus')->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('jirau_columns')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('task');
            $table->string('priority', 20)->default('medium');
            $table->unsignedTinyInteger('estimate')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('triaged_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['jirau_id', 'column_id', 'sort_order']);
            $table->index(['jirau_id', 'archived_at']);
        });

        Schema::create('jirau_card_label', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->foreignId('label_id')->constrained('jirau_labels')->cascadeOnDelete();
            $table->primary(['card_id', 'label_id']);
        });

        Schema::create('jirau_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('jirau_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('jirau_cards')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('jirau_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jirau_id')->constrained('jiraus')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['jirau_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jirau_members');
        Schema::dropIfExists('jirau_comments');
        Schema::dropIfExists('jirau_checklist_items');
        Schema::dropIfExists('jirau_card_label');
        Schema::dropIfExists('jirau_cards');
        Schema::dropIfExists('jirau_labels');
        Schema::dropIfExists('jirau_columns');
        Schema::dropIfExists('jiraus');
    }
};
