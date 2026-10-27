<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_active');
            $table->index('sort_order');
        });

        $order = 0;
        DB::table('jiraus')->orderBy('name')->orderBy('id')->get(['id'])->each(function ($row) use (&$order): void {
            $order += 10;
            DB::table('jiraus')->where('id', $row->id)->update(['sort_order' => $order]);
        });
    }

    public function down(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
