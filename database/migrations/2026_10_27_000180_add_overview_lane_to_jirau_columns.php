<?php

use App\Models\JirauColumn;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jirau_columns', function (Blueprint $table) {
            $table->string('overview_lane', 20)->nullable()->after('is_done');
        });

        JirauColumn::query()->each(function (JirauColumn $column): void {
            $column->forceFill([
                'overview_lane' => JirauColumn::inferOverviewLane(
                    (bool) $column->is_inbox,
                    (bool) $column->is_done,
                    $column->slug,
                ),
            ])->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('jirau_columns', function (Blueprint $table) {
            $table->dropColumn('overview_lane');
        });
    }
};
