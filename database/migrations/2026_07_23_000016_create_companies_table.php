<?php

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('document', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website_url')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('company')
                ->constrained('companies')
                ->nullOnDelete();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('name')
                ->constrained('companies')
                ->nullOnDelete();
        });

        $this->backfillCompanies();
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });

        Schema::dropIfExists('companies');
    }

    private function backfillCompanies(): void
    {
        $names = Contact::query()
            ->whereNotNull('company')
            ->where('company', '!=', '')
            ->pluck('company')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => Str::lower($name));

        foreach ($names as $name) {
            $company = Company::query()->firstOrCreate(
                ['name' => $name],
                ['status' => 'active']
            );

            Contact::query()
                ->whereRaw('LOWER(TRIM(company)) = ?', [Str::lower($name)])
                ->update([
                    'company_id' => $company->id,
                    'company' => $company->name,
                ]);
        }

        // Projetos ligados a um único contato com empresa: herdam a empresa.
        $pairs = DB::table('contact_project')
            ->join('contacts', 'contacts.id', '=', 'contact_project.contact_id')
            ->whereNotNull('contacts.company_id')
            ->select('contact_project.project_id', 'contacts.company_id')
            ->get()
            ->groupBy('project_id');

        foreach ($pairs as $projectId => $rows) {
            $companyIds = $rows->pluck('company_id')->unique()->values();
            if ($companyIds->count() === 1) {
                DB::table('projects')
                    ->where('id', $projectId)
                    ->whereNull('company_id')
                    ->update(['company_id' => $companyIds->first()]);
            }
        }
    }
};
