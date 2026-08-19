<?php

namespace Tests\Feature\Admin;

use App\Enums\OpportunityStage;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\OpportunityStageEvent;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CrmConsolidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['telegram_chat_id' => '999001'])->save();
        config(['services.telegram.bot_token' => 'test-token']);
    }

    public function test_opportunity_can_exist_without_company(): void
    {
        $contact = Contact::factory()->create(['company_id' => null, 'company' => null]);

        $this->actingAs($this->admin)->post(route('admin.opportunities.store'), [
            'contact_id' => $contact->id,
            'company_id' => '',
            'title' => 'Lead avulso',
            'stage' => 'lead',
        ])->assertRedirect();

        $this->assertDatabaseHas('opportunities', [
            'title' => 'Lead avulso',
            'contact_id' => $contact->id,
            'company_id' => null,
        ]);
    }

    public function test_changing_stage_writes_history(): void
    {
        $opportunity = Opportunity::factory()->create(['stage' => OpportunityStage::Lead]);

        $this->assertDatabaseHas('opportunity_stage_events', [
            'opportunity_id' => $opportunity->id,
            'to_stage' => 'lead',
            'from_stage' => null,
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.opportunities.stage', $opportunity), [
                'stage' => 'qualified',
                'ordered_ids' => [$opportunity->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('opportunity_stage_events', [
            'opportunity_id' => $opportunity->id,
            'from_stage' => 'lead',
            'to_stage' => 'qualified',
            'user_id' => $this->admin->id,
        ]);

        $this->assertGreaterThanOrEqual(2, OpportunityStageEvent::query()->where('opportunity_id', $opportunity->id)->count());
    }

    public function test_company_page_lists_opportunity_linked_only_via_contact(): void
    {
        $company = Company::factory()->create(['name' => 'Orfa Ltda']);
        $contact = Contact::factory()->create([
            'company_id' => $company->id,
            'company' => $company->name,
        ]);
        Opportunity::factory()->create([
            'contact_id' => $contact->id,
            'company_id' => null,
            'title' => 'Opp sem empresa directa',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee('Opp sem empresa directa', false);
    }

    public function test_dashboard_inbox_and_global_search(): void
    {
        $contact = Contact::factory()->create(['name' => 'Zelia Busca']);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Próximas acções', false);

        $this->actingAs($this->admin)
            ->get(route('admin.search', ['q' => 'Zelia']))
            ->assertOk()
            ->assertSee('Zelia Busca', false);
    }

    public function test_opportunities_csv_export(): void
    {
        Opportunity::factory()->create(['title' => 'CSV Deal']);

        $response = $this->actingAs($this->admin)->get(route('admin.opportunities.export'));

        $response->assertOk();
        $this->assertStringContainsString('CSV Deal', $response->streamedContent());
    }

    public function test_backfill_copies_company_text_and_opportunity_fk(): void
    {
        $contact = Contact::factory()->create([
            'company' => 'Nova Empresa Backfill',
            'company_id' => null,
        ]);
        $opportunity = Opportunity::factory()->create([
            'contact_id' => $contact->id,
            'company_id' => null,
        ]);

        $this->artisan('crm:backfill-companies')->assertSuccessful();

        $contact->refresh();
        $this->assertNotNull($contact->company_id);
        $this->assertSame($contact->company_id, $opportunity->fresh()->company_id);
    }

    public function test_follow_up_notifies_stale_opportunities(): void
    {
        app(\App\Services\SettingService::class)->putMany([
            'telegram_notify_chat_id' => '999001',
        ]);

        Opportunity::factory()->create([
            'title' => 'Negociação parada',
            'stage' => OpportunityStage::Negotiation,
            'updated_at' => now()->subDays(10),
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $this->artisan('opportunities:follow-up', ['--days' => 7])
            ->expectsOutputToContain('1 avisos enviados')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_featured_project_appears_in_home_highlight(): void
    {
        Project::factory()->public()->create([
            'name' => 'Case Comum',
            'featured_on_home' => false,
            'sort_order' => 1,
        ]);
        Project::factory()->public()->create([
            'name' => 'Case Destaque Home',
            'featured_on_home' => true,
            'featured_sort' => 1,
            'sort_order' => 99,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Em destaque', false)
            ->assertSee('Case Destaque Home', false)
            ->assertSee('Case Comum', false);
    }

    public function test_contact_activity_form_prefills_task_from_query(): void
    {
        $contact = Contact::factory()->create();
        $task = \App\Models\Task::factory()->create([
            'contact_id' => $contact->id,
            'title' => 'Reunião prefilling',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', ['contact' => $contact, 'task_id' => $task->id]))
            ->assertOk()
            ->assertSee('selected', false)
            ->assertSee('Reunião prefilling', false);
    }
}
