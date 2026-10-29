<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->boolean('is_sample')->default(false)->after('verification_code')
                ->comment('Amostra visual de teste; não é depoimento de cliente.');
            $table->index(['is_sample', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $table) {
            $table->dropIndex(['is_sample', 'status']);
            $table->dropColumn('is_sample');
        });
    }
};
