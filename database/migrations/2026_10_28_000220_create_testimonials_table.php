<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('moderated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedTinyInteger('original_stars')->nullable();
            $table->text('original_body')->nullable();
            $table->string('original_display_name', 120)->nullable();
            $table->unsignedTinyInteger('published_stars')->nullable();
            $table->text('published_body')->nullable();
            $table->string('published_display_name', 120)->nullable();
            $table->string('invite_token_hash', 64)->nullable()->unique();
            $table->timestamp('invite_expires_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('submitted_ip', 45)->nullable();
            $table->string('submitted_user_agent', 512)->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->string('moderation_notes', 500)->nullable();
            $table->char('source_digest', 64)->nullable();
            $table->char('result_digest', 64)->nullable();
            $table->char('seal', 64)->nullable();
            $table->string('verification_code', 16)->nullable()->unique();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['contact_id', 'status']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};
