<?php

namespace Tests\Feature\Admin;

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
            ->assertSee('Ideias & rascunhos', false)
            ->assertSee('Ideia dashboard', false)
            ->assertSee('Rascunho livre', false)
            ->assertSee('Novo post-it', false);
    }

    public function test_admin_can_create_update_and_delete_blank_friendly_note(): void
    {
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
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('idea_notes', [
            'id' => $note->id,
            'title' => 'App mobile',
            'body' => 'Explorar onboarding',
            'color' => 'blue',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.idea-notes.destroy', $note))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertDatabaseMissing('idea_notes', ['id' => $note->id]);
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
}
