<?php

namespace Tests\Feature\Admin;

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
            'phone' => '+55 38999999999',
            'company' => 'Cliente SA',
            'role' => 'Diretora',
            'preferred_channel' => 'email',
            'status' => 'lead',
            'source' => 'manual',
            'notes' => 'Interessada em BI',
        ]);

        $contact = Contact::query()->where('email', 'maria@cliente.com')->first();
        $this->assertNotNull($contact);
        $response->assertRedirect(route('admin.contacts.show', $contact));

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contact))
            ->assertOk()
            ->assertSee('Maria Cliente', false)
            ->assertSee('Interessada em BI', false);
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
            ->assertSee('Pacote BI municipal', false)
            ->assertSee('Servlitcys CRM', false);
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
