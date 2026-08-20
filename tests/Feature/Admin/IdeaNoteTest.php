<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Contact;
use App\Models\IdeaNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_dashboard_shows_idea_notes_board(): void
    {
        IdeaNote::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Ideia dashboard',
            'body' => 'Rascunho livre',
            'color' => 'mint',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ideias e rascunhos', false)
            ->assertSee('Ideia dashboard', false)
            ->assertSee('Rascunho livre', false)
            ->assertSee('Novo post-it', false)
            ->assertSee('data-idea-board', false)
            ->assertSee('data-idea-drag', false)
            ->assertSee('Alocar a', false)
            ->assertSee('name="company_id"', false)
            ->assertSee('name="contact_id"', false);
    }

    public function test_admin_can_create_update_and_delete_blank_friendly_note(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa Post-it']);
        $contact = Contact::factory()->create([
            'name' => 'Contato Post-it',
            'company_id' => $company->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.idea-notes.store'), [
                'title' => null,
                'body' => null,
                'color' => 'rose',
            ])
            ->assertRedirect();

        $note = IdeaNote::query()->first();
        $this->assertNotNull($note);
        $this->assertSame($this->admin->id, $note->user_id);
        $this->assertSame('rose', $note->color->value);
        $this->assertTrue($note->isBlank());

        $this->actingAs($this->admin)
            ->put(route('admin.idea-notes.update', $note), [
                'title' => 'App mobile',
                'body' => 'Explorar onboarding',
                'color' => 'blue',
                'company_id' => $company->id,
                'contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('idea_notes', [
            'id' => $note->id,
            'title' => 'App mobile',
            'body' => 'Explorar onboarding',
            'color' => 'blue',
            'company_id' => $company->id,
            'contact_id' => $contact->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.show', $company))
            ->assertOk()
            ->assertSee('Ideias / post-its', false)
            ->assertSee('App mobile', false)
            ->assertSee('Explorar onboarding', false)
            ->assertSee('+ Novo post-it', false)
            ->assertSee('postit-blue', false);

        $this->actingAs($this->admin)
            ->get(route('admin.contacts.show', $contact))
            ->assertOk()
            ->assertSee('Ideias / post-its', false)
            ->assertSee('App mobile', false)
            ->assertSee('+ Novo post-it', false)
            ->assertSee('postit-blue', false);

        $this->actingAs($this->admin)
            ->delete(route('admin.idea-notes.destroy', $note))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseMissing('idea_notes', ['id' => $note->id]);
    }

    public function test_contact_allocation_inherits_company(): void
    {
        $company = Company::factory()->create(['name' => 'Empresa Herdada']);
        $contact = Contact::factory()->create([
            'name' => 'Contato Herdado',
            'company_id' => $company->id,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.idea-notes.store'), [
                'title' => 'Só contato',
                'body' => 'Herdar empresa',
                'color' => 'mint',
                'contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('idea_notes', [
            'title' => 'Só contato',
            'contact_id' => $contact->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_admin_can_create_note_from_company_or_contact_page(): void
    {
        $company = Company::factory()->create();
        $contact = Contact::factory()->create(['company_id' => $company->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.idea-notes.store'), [
                'color' => 'amber',
                'company_id' => $company->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('idea_notes', [
            'company_id' => $company->id,
            'contact_id' => null,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.idea-notes.store'), [
                'color' => 'amber',
                'contact_id' => $contact->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('idea_notes', [
            'contact_id' => $contact->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_admin_can_change_idea_note_color_instantly(): void
    {
        $note = IdeaNote::factory()->create([
            'user_id' => $this->admin->id,
            'color' => 'amber',
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.idea-notes.color', $note), [
                'color' => 'mint',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'color' => 'mint',
            ]);

        $this->assertDatabaseHas('idea_notes', [
            'id' => $note->id,
            'color' => 'mint',
        ]);
    }

    public function test_admin_can_reorder_idea_notes(): void
    {
        $first = IdeaNote::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Primeiro',
            'sort_order' => 10,
        ]);
        $second = IdeaNote::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Segundo',
            'sort_order' => 20,
        ]);

        $this->actingAs($this->admin)
            ->putJson(route('admin.idea-notes.reorder'), [
                'ids' => [$first->id, $second->id],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame(20, (int) $first->fresh()->sort_order);
        $this->assertSame(10, (int) $second->fresh()->sort_order);
    }
}
