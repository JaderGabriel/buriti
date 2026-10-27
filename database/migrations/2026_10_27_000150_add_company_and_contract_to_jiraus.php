<?php

use App\Models\Jirau;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->string('contract_name', 120)->nullable()->after('company_id');
        });

        Project::query()
            ->whereNotNull('company_id')
            ->select(['id', 'company_id'])
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    Jirau::query()
                        ->where('project_id', $project->id)
                        ->whereNull('company_id')
                        ->update(['company_id' => $project->company_id]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('jiraus', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('contract_name');
        });
    }
};
