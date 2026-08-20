<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idea_notes', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->index(['company_id', 'sort_order']);
            $table->index(['contact_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('idea_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('contact_id');
        });
    }
};
