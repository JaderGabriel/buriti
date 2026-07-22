<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        ContactMessage::factory()->create();
        Project::factory()->create();
        Task::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard', false);
    }

    public function test_admin_can_read_and_mark_contact_messages(): void
    {
        $message = ContactMessage::factory()->create([
            'subject' => 'Assunto importante',
            'read_at' => null,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Assunto importante', false);

        $this->actingAs($this->admin)
            ->get(route('admin.messages.show', $message))
            ->assertOk();

        $this->assertNotNull($message->fresh()->read_at);
    }

    public function test_admin_can_create_project(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->post(route('admin.projects.store'), [
            'name' => 'Novo Projeto',
            'information' => 'Descrição do projeto',
            'website_url' => 'https://buriti.dev.br',
            'github_url' => 'https://github.com/JaderGabriel/exemplo',
            'status' => 'active',
            'is_public' => '1',
            'sort_order' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('admin.projects.index'));
        $this->assertDatabaseHas('projects', [
            'name' => 'Novo Projeto',
            'is_public' => 1,
        ]);

        $project = Project::query()->where('name', 'Novo Projeto')->first();
        $this->assertNotNull($project?->logo_path);
        Storage::disk('public')->assertExists($project->logo_path);
    }

    public function test_admin_can_create_and_update_task(): void
    {
        $project = Project::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.tasks.store'), [
            'project_id' => $project->id,
            'title' => 'Preparar proposta',
            'description' => 'Detalhar escopo',
            'status' => 'todo',
            'priority' => 'high',
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.tasks.index'));

        $task = Task::query()->where('title', 'Preparar proposta')->firstOrFail();

        $this->actingAs($this->admin)->put(route('admin.tasks.update', $task), [
            'project_id' => $project->id,
            'title' => 'Preparar proposta',
            'description' => 'Escopo revisado',
            'status' => 'doing',
            'priority' => 'high',
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
        ])->assertRedirect(route('admin.tasks.index'));

        $this->assertSame('doing', $task->fresh()->status->value);
    }

    public function test_admin_can_update_settings(): void
    {
        $this->actingAs($this->admin)->put(route('admin.settings.update'), [
            'contact_email' => 'jadergabriel8@gmail.com',
            'contact_phone' => '+55 38 99175-8416',
            'contact_whatsapp' => '+55 38991758416',
            'linkedin_url' => 'https://www.linkedin.com/in/jadergabriel/',
            'github_url' => 'https://github.com/JaderGabriel',
            'telegram_url' => 'https://t.me/JaderGabriel',
            'telegram_handle' => '@JaderGabriel',
            'google_calendar_url' => 'https://calendar.google.com/calendar/u/0/r',
            'google_calendar_embed' => 'https://calendar.google.com/calendar/embed?src=example',
        ])->assertRedirect(route('admin.settings.edit'));

        $settings = app(SettingService::class)->all();

        $this->assertSame('jadergabriel8@gmail.com', $settings['contact_email']);
        $this->assertSame('+55 38991758416', $settings['contact_whatsapp']);
        $this->assertSame('@JaderGabriel', $settings['telegram_handle']);
        $this->assertSame(
            'https://calendar.google.com/calendar/embed?src=example',
            app(SettingService::class)->calendarSrc()
        );
    }
}
