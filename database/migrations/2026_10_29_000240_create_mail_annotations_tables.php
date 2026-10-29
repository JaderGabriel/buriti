<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40);
            $table->string('slug', 40)->unique();
            $table->string('color', 20)->default('slate');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('mail_records', function (Blueprint $table) {
            $table->id();
            $table->string('account', 180);
            $table->string('message_key', 250);
            $table->string('rfc_message_id', 250)->nullable();
            $table->string('folder', 200);
            $table->unsignedInteger('uid');
            $table->string('subject', 180)->nullable();
            $table->string('from_address', 180)->nullable();
            $table->string('marker', 20)->nullable();
            $table->timestamps();

            $table->unique(['account', 'message_key']);
            $table->index(['account', 'rfc_message_id']);
            $table->index(['account', 'folder', 'uid']);
        });

        Schema::create('mail_record_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_record_id')->constrained('mail_records')->cascadeOnDelete();
            $table->foreignId('mail_label_id')->constrained('mail_labels')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mail_record_id', 'mail_label_id']);
        });

        Schema::create('mail_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mail_record_id')->constrained('mail_records')->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->timestamps();
            $table->unique(['mail_record_id', 'linkable_type', 'linkable_id']);
            $table->index(['linkable_type', 'linkable_id']);
        });

        $now = now();
        DB::table('mail_labels')->insert([
            ['name' => 'Comercial', 'slug' => 'comercial', 'color' => 'blue', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Proposta', 'slug' => 'proposta', 'color' => 'amber', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Contrato', 'slug' => 'contrato', 'color' => 'mint', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Interno', 'slug' => 'interno', 'color' => 'slate', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_links');
        Schema::dropIfExists('mail_record_label');
        Schema::dropIfExists('mail_records');
        Schema::dropIfExists('mail_labels');
    }
};
