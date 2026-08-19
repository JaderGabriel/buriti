<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('expected_close_at');
            $table->index(['stage', 'updated_at']);
            $table->index(['company_id', 'stage']);
        });

        Schema::create('opportunity_stage_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20);
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['opportunity_id', 'changed_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('featured_on_home')->default(false)->after('is_public');
            $table->unsignedInteger('featured_sort')->default(0)->after('sort_order');
            $table->index(['is_public', 'featured_on_home', 'featured_sort']);
        });

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->index(['contact_id', 'happened_at']);
            $table->index(['opportunity_id', 'happened_at']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['status', 'due_at']);
        });

        foreach (DB::table('opportunities')->orderBy('id')->get() as $row) {
            $companyId = DB::table('contacts')->where('id', $row->contact_id)->value('company_id');
            if ($companyId) {
                DB::table('opportunities')->where('id', $row->id)->update(['company_id' => $companyId]);
            }

            DB::table('opportunity_stage_events')->insert([
                'opportunity_id' => $row->id,
                'user_id' => null,
                'from_stage' => null,
                'to_stage' => $row->stage,
                'changed_at' => $row->created_at ?? now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'due_at']);
        });

        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropIndex(['contact_id', 'happened_at']);
            $table->dropIndex(['opportunity_id', 'happened_at']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['is_public', 'featured_on_home', 'featured_sort']);
            $table->dropColumn(['featured_on_home', 'featured_sort']);
        });

        Schema::dropIfExists('opportunity_stage_events');

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropIndex(['stage', 'updated_at']);
            $table->dropIndex(['company_id', 'stage']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('sort_order');
        });
    }
};
