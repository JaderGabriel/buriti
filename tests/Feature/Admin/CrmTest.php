<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_admin_can_create_and_view_contact(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.contacts.store'), [
            'name' => 'Maria Cliente',
            'email' => 'maria@cliente.com',
            'phone_country' => 'BR',
            'phone_number' => '38999999999',
            'company' => 'Cliente SA',
            'role' => 'Diretora',
            'preferred_channel' => 'email',
            'status' => 'lead',
            'source' => 'manual',
            'notes' => 'Interessada em BI',
        ]);

        $contact = Contact::query()->where('email', 'maria@cliente.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('+55 38 99999-9999', $contact->phone);
        $this->assertDatabaseHas('companies', ['name' => 'Cliente SA']);
        $this->assertNotNull($contact->company_id);
        $response->assertRedirect(route('admin.contacts.show', $contact));

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contact))
            ->assertOk()
            ->assertSee('Maria Cliente', false)
            ->assertSee('Interessada em BI', false)
            ->assertSee('Cliente SA', false);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.index', ['view' => 'phonebook']))
            ->assertOk()
            ->assertSee('Agenda', false)
            ->assertSee('phonebook', false)
            ->assertSee('Maria Cliente', false)
            ->assertSee('+55 38 99999-9999', false);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.index', ['view' => 'phonebook', 'letter' => 'M']))
            ->assertOk()
            ->assertSee('Maria Cliente', false);
    }

    public function test_admin_can_manage_company_with_contacts_projects_and_opportunities(): void
    {
        $company = Company::factory()->create(['name' => 'Prefeitura Alpha']);
        $contactA = Contact::factory()->create([
            'name' => 'Ana Alpha',
            'company_id' => $company->id,
            'company' => $company->name,
        ]);
        $contactB = Contact::factory()->create([
            'name' => 'Bruno Alpha',
            'company_id' => $company->id,
            'company' => $company->name,
        ]);
        $project = Project::factory()->create([
            'name' => 'GIDE Alpha',
            'company_id' => $company->id,
        ]);
        $contactA->projects()->attach($project->id);

        Opportunity::factory()->create([
            'contact_id' => $contactA->id,
            'project_id' => $project->id,
            'title' => 'Opp só da Ana',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee('Prefeitura Alpha', false)
            ->assertSee('Ana Alpha', false)
            ->assertSee('Bruno Alpha', false)
            ->assertSee('GIDE Alpha', false)
            ->assertSee('Opp só da Ana', false);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contactA))
            ->assertOk()
            ->assertSee(route('admin.companies.show', $company), false);
    }

    public function test_admin_can_update_opportunity_stage_via_form_and_patch(): void
    {
        $contact = Contact::factory()->create();
        $opportunity = Opportunity::factory()->create([
            'contact_id' => $contact->id,
            'title' => 'Negócio funil',
            'stage' => 'lead',
        ]);

        $this->actingAs($this->admin)->put(route('admin.opportunities.update', $opportunity), [
            'contact_id' => $contact->id,
            'title' => 'Negócio funil',
            'description' => 'Atualizado',
            'stage' => 'negotiation',
            'value' => '9000',
            'expected_close_at' => now()->addWeeks(2)->toDateString(),
        ])->assertRedirect(route('admin.opportunities.index', ['view' => 'board']));

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'negotiation',
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.opportunities.stage', $opportunity), ['stage' => 'proposal'])
            ->assertOk()
            ->assertJsonPath('stage', 'proposal')
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'stage' => 'proposal',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.opportunities.index'))
            ->assertOk()
            ->assertSee('data-opportunity-board', false)
            ->assertSee('data-stage-url-template', false)
            ->assertSee('Solte aqui para mover', false);
    }

    public function test_admin_can_create_opportunity_linked_to_contact_and_project(): void
    {
        $contact = Contact::factory()->create();
        $project = Project::factory()->create(['name' => 'Servlitcys CRM']);

        $this->actingAs($this->admin)->post(route('admin.opportunities.store'), [
            'contact_id' => $contact->id,
            'project_id' => $project->id,
            'title' => 'Pacote BI municipal',
            'description' => 'Proposta inicial',
            'stage' => 'proposal',
            'value' => '15000.50',
            'expected_close_at' => now()->addMonth()->toDateString(),
        ])->assertRedirect(route('admin.contacts.show', $contact));

        $this->assertDatabaseHas('opportunities', [
            'contact_id' => $contact->id,
            'project_id' => $project->id,
            'title' => 'Pacote BI municipal',
            'stage' => 'proposal',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.opportunities.index'))
            ->assertOk()
            ->assertSee('Pipeline de oportunidades', false)
            ->assertSee('Lead → Contrato', false)
            ->assertSee('Pacote BI municipal', false)
            ->assertSee('Servlitcys CRM', false)
            ->assertSee('crm-board', false);
    }

    public function test_admin_can_register_activity_and_attach_project(): void
    {
        $contact = Contact::factory()->create();
        $project = Project::factory()->create(['name' => 'GIDE']);
        $opportunity = Opportunity::factory()->create([
            'contact_id' => $contact->id,
            'title' => 'Opp GIDE',
        ]);

        $this->actingAs($this->admin)->post(route('admin.contacts.activities.store', $contact), [
            'type' => 'call',
            'subject' => 'Ligação de discovery',
            'body' => 'Conversámos sobre catracas',
            'opportunity_id' => $opportunity->id,
            'happened_at' => now()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $this->assertDatabaseHas('crm_activities', [
            'contact_id' => $contact->id,
            'opportunity_id' => $opportunity->id,
            'type' => 'call',
            'subject' => 'Ligação de discovery',
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->post(route('admin.contacts.projects.attach', $contact), [
            'project_id' => $project->id,
        ])->assertRedirect();

        $this->assertTrue($contact->fresh()->projects->contains($project));

        $this->actingAs($this->admin)
            ->delete(route('admin.contacts.projects.detach', [$contact, $project]))
            ->assertRedirect();

        $this->assertFalse($contact->fresh()->projects->contains($project));
    }

    public function test_admin_can_link_message_to_contact(): void
    {
        $message = ContactMessage::factory()->create([
            'name' => 'Lead Sem Contato',
            'email' => 'lead@semcontato.com',
            'contact_id' => null,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.messages.link-contact', $message));

        $contact = Contact::query()->where('email', 'lead@semcontato.com')->first();
        $this->assertNotNull($contact);
        $response->assertRedirect(route('admin.contacts.show', $contact));
        $this->assertSame($contact->id, $message->fresh()->contact_id);
    }

    public function test_task_can_be_linked_to_contact(): void
    {
        $contact = Contact::factory()->create(['name' => 'Contato Agenda']);
        $project = Project::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.tasks.store'), [
            'title' => 'Reunião com contato',
            'description' => 'Alinhamento',
            'project_id' => $project->id,
            'contact_id' => $contact->id,
            'status' => 'todo',
            'priority' => 'high',
            'want_meet' => '1',
        ])->assertRedirect(route('admin.tasks.index'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'Reunião com contato',
            'contact_id' => $contact->id,
            'project_id' => $project->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contact))
            ->assertOk()
            ->assertSee('Reunião com contato', false);
    }

    public function test_dashboard_shows_crm_counters(): void
    {
        Contact::factory()->count(2)->create();
        Opportunity::factory()->create(['stage' => 'qualified']);
        Opportunity::factory()->won()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Contatos CRM', false)
            ->assertSee('Oportunidades abertas', false)
            ->assertSee('>Contatos</a>', false)
            ->assertSee('Comercial', false)
            ->assertSee('Entrega', false)
            ->assertSee('Sistema', false);
    }

    public function test_activity_destroy_belongs_to_contact(): void
    {
        $contact = Contact::factory()->create();
        $activity = CrmActivity::factory()->create(['contact_id' => $contact->id]);
        $other = Contact::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.contacts.activities.destroy', [$other, $activity]))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->delete(route('admin.contacts.activities.destroy', [$contact, $activity]))
            ->assertRedirect();

        $this->assertDatabaseMissing('crm_activities', ['id' => $activity->id]);
    }
}
