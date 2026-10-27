<?php

namespace Tests\Feature\Admin;

use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\SettingService;
use App\Support\RuntimeVersions;
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

    public function test_sidebar_lists_modules_and_topbar_lists_submodules(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Módulos"', false)
            ->assertSee('data-admin-module-pin', false)
            ->assertSee('Módulo Início', false)
            ->assertSee('Comercial', false)
            ->assertSee('Entrega', false)
            ->assertSee('Jirau', false)
            ->assertSee('Sistema', false)
            ->assertSee('admin-nav__group--home', false)
            ->assertSee('admin-nav__group--comercial', false)
            ->assertSee('admin-nav__group--entrega', false)
            ->assertSee('admin-nav__group--jirau', false)
            ->assertSee('admin-nav__group--sistema', false)
            ->assertSee('admin-nav__sub', false)
            ->assertSee('admin-nav__sublink', false)
            ->assertDontSee('admin-nav__text">Mensagens', false)
            ->assertDontSee('admin-nav__text">Usuários', false);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('admin-modulebar', false)
            ->assertSee('Módulo Comercial', false)
            ->assertSee('>Mensagens</span>', false)
            ->assertSee('>Empresas</span>', false)
            ->assertSee('>Contatos</span>', false)
            ->assertSee('>Oportunidades</span>', false)
            ->getContent();

        $this->assertStringContainsString('admin-nav__sublink', $html);
        $this->assertMatchesRegularExpression('/admin-nav__sublink[^>]*>[\s\S]*Mensagens/', $html);
    }

    public function test_dashboard_loads_for_authenticated_user(): void
    {
        ContactMessage::factory()->create();
        Project::factory()->create();
        Task::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Início', false);
    }

    public function test_chrome_lives_in_topbar_and_meetings_in_sidebar(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Novo Meet', false)
            ->assertSee('Novo Teams', false)
            ->assertSee('Novo Jitsi', false)
            ->assertSee('aria-label="Ver site"', false)
            ->assertSee('aria-label="Sair"', false)
            ->assertSee('data-theme-toggle', false)
            ->assertSee('meet.google.com/new', false)
            ->assertSee('teams.microsoft.com/l/meeting/new', false)
            ->assertSee('meet.jit.si', false)
            ->getContent();

        $sidebar = strpos($html, 'data-admin-sidebar');
        $stage = strpos($html, 'admin-stage');
        $meetings = strpos($html, 'admin-meetings');
        $topbar = strpos($html, 'admin-topbar__account');
        $chrome = strpos($html, 'admin-chrome');
        $bell = strpos($html, 'admin-notify');

        $this->assertNotFalse($sidebar);
        $this->assertNotFalse($stage);
        $this->assertNotFalse($meetings);
        $this->assertNotFalse($topbar);
        $this->assertNotFalse($chrome);
        $this->assertNotFalse($bell);
        $this->assertGreaterThan($sidebar, $meetings);
        $this->assertLessThan($stage, $meetings);
        $this->assertGreaterThan($stage, $topbar);
        $this->assertGreaterThan($topbar, $bell);
        $this->assertGreaterThan($bell, $chrome);
        $this->assertLessThan(strpos($html, 'admin-content'), strpos($html, 'admin-topbar'));
        $this->assertSame(1, substr_count($html, 'admin-chrome'));
        $this->assertSame(1, substr_count($html, 'admin-meetings'));
        $this->assertSame(0, substr_count($html, 'admin-shortcuts'));
    }

    public function test_sidebar_shows_runtime_versions_below_settings(): void
    {
        $versions = RuntimeVersions::labels();

        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Sistema', false)
            ->assertSee('admin-versions', false)
            ->assertSee('Laravel '.$versions['laravel'], false)
            ->assertSee('PHP '.$versions['php'], false)
            ->assertSee('Server '.$versions['server'], false)
            ->assertSee(RuntimeVersions::release(), false)
            ->assertDontSee('Sistema Linux', false)
            ->assertSee('data-admin-nav-toggle', false)
            ->assertSee('admin-modulebar', false)
            ->getContent();

        $sistemaPos = strpos($html, '>Sistema<');
        $versionsPos = strpos($html, 'admin-versions');

        $this->assertNotFalse($sistemaPos);
        $this->assertNotFalse($versionsPos);
        $this->assertGreaterThan($sistemaPos, $versionsPos);

        $this->actingAs($this->admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Configurações', false);
    }

    public function test_admin_can_read_and_mark_contact_messages(): void
    {
        $message = ContactMessage::factory()->create([
            'subject' => 'Assunto importante',
            'read_at' => null,
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('admin.messages.index'))
            ->assertOk()
            ->assertSee('Mensagens', false)
            ->assertSee('Mensageria BURI-TI', false)
            ->assertSee('Assunto importante', false)
            ->getContent();

        $this->assertStringContainsString('class="messenger-shell"', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*messenger-shell[^"]*-mx-/',
            $html,
        );

        $this->actingAs($this->admin)
            ->get(route('admin.messages.show', $message))
            ->assertOk()
            ->assertSee('Assunto importante', false)
            ->assertSee('Responder por e-mail', false);

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
            'repo_is_private' => '1',
            'sort_order' => 1,
            'logo' => UploadedFile::fake()->image('logo.png'),
        ]);

        $response->assertRedirect(route('admin.projects.index'));
        $this->assertDatabaseHas('projects', [
            'name' => 'Novo Projeto',
            'is_public' => 1,
            'repo_is_private' => 1,
        ]);

        $project = Project::query()->where('name', 'Novo Projeto')->first();
        $this->assertNotNull($project?->logo_path);
        $this->assertFalse($project->exposesPublicLinks());
        Storage::disk('public')->assertExists($project->logo_path);

        $this->actingAs($this->admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Portfólio operacional', false)
            ->assertSee('pm-board', false)
            ->assertSee('Novo Projeto', false);

        $this->actingAs($this->admin)
            ->get(route('admin.projects.index', ['view' => 'list']))
            ->assertOk()
            ->assertSee('pm-table', false)
            ->assertSee('Novo Projeto', false);
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
            'meet_url' => 'https://meet.google.com/abc-defg-hij',
            'want_meet' => '1',
        ])->assertRedirect(route('admin.tasks.index'));

        $task->refresh();
        $this->assertSame('doing', $task->status->value);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $task->meet_url);
        $this->assertTrue($task->want_meet);
    }

    public function test_tasks_index_supports_calendar_and_board_views(): void
    {
        Task::factory()->create([
            'title' => 'Reunião de escopo',
            'due_at' => now()->startOfMonth()->addDays(3)->setTime(10, 0),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Calendário de atividades', false)
            ->assertSee('task-calendar', false)
            ->assertSee('task-planner__split', false)
            ->assertSee('task-planner__google', false)
            ->assertSee('Exportar agenda', false)
            ->assertSee('Reunião de escopo', false);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Novo Meet', false)
            ->assertSee('Novo Teams', false)
            ->assertSee('Novo Jitsi', false)
            ->assertSee('meet.google.com/new', false)
            ->assertSee('teams.microsoft.com/l/meeting/new', false)
            ->assertSee('meet.jit.si', false);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.index', ['view' => 'board']))
            ->assertOk()
            ->assertSee('task-board', false)
            ->assertSee('A fazer', false);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.index', ['view' => 'agenda']))
            ->assertOk()
            ->assertSee('task-agenda', false);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.index', ['view' => 'list']))
            ->assertOk()
            ->assertSee('task-list', false)
            ->assertSee('data-task-open', false)
            ->assertSee('task-edit-dialog', false);
    }

    public function test_task_click_opens_edit_modal_and_can_complete(): void
    {
        $task = Task::factory()->create([
            'title' => 'Revisar proposta modal',
            'status' => 'todo',
            'due_at' => now()->addDay()->startOfHour(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.edit', $task))
            ->assertOk()
            ->assertSee('Revisar proposta modal', false)
            ->assertSee('Marcar concluída', false)
            ->assertSee('Salvar', false);

        $this->actingAs($this->admin)
            ->from(route('admin.dashboard'))
            ->post(route('admin.tasks.complete', $task), [
                'return_to' => 'dashboard',
            ])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertTrue($task->fresh()->status->isDone());

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-dash-board', false)
            ->assertSee('data-dash-widget="messages"', false)
            ->assertSee('data-dash-widget="contacts"', false)
            ->assertSee('data-dash-widget="tasks"', false)
            ->assertSee('data-dash-widget="conduct"', false)
            ->assertSee('data-dash-widget="ideas"', false)
            ->assertSee('data-dash-widget="calendar"', false)
            ->assertSee('data-dash-storage="buriti-dash-end-v1"', false)
            ->assertSee('data-span="1"', false)
            ->assertSee('data-span-default="1"', false)
            ->assertSee('data-dash-handle', false)
            ->assertSee('Repor disposição', false);
    }

    public function test_task_google_sync_redirects_to_calendar_when_api_missing(): void
    {
        $task = Task::factory()->create([
            'title' => 'Kickoff Meet',
            'want_meet' => true,
            'due_at' => now()->addDay()->startOfHour(),
        ]);

        $response = $this->actingAs($this->admin)
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.google', $task));

        $response->assertRedirect(route('admin.tasks.index'));
        $response->assertSessionHas('error');
    }

    public function test_admin_can_export_month_agenda_as_ics(): void
    {
        $due = now()->startOfMonth()->addDays(2)->setTime(10, 30);

        Task::factory()->create([
            'title' => 'Exportável ICS',
            'due_at' => $due,
            'meet_url' => 'https://meet.google.com/abc-defg-hij',
        ]);

        Task::factory()->create([
            'title' => 'Fora do mês',
            'due_at' => $due->copy()->addMonths(2),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.tasks.export', ['month' => $due->format('Y-m')]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/calendar; charset=utf-8');
        $response->assertSee('BEGIN:VCALENDAR', false);
        $response->assertSee('Exportável ICS', false);
        $response->assertDontSee('Fora do mês', false);
        $response->assertSee('meet.google.com/abc-defg-hij', false);
    }

    public function test_settings_contact_card_uses_full_layout_width(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Contato da empresa', false)
            ->assertSee('settings-contact-layout', false)
            ->getContent();

        $this->assertStringNotContainsString('max-w-3xl', $html);
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
            'google_calendar_embed' => 'https://calendar.google.com/calendar/embed?src=example%40group.calendar.google.com',
            'google_calendar_id' => 'primary',
            'google_auto_sync' => '1',
        ])->assertRedirect(route('admin.settings.edit').'#google-integration');

        $settings = app(SettingService::class)->all();

        $this->assertSame('jadergabriel8@gmail.com', $settings['contact_email']);
        $this->assertSame('+55 (38) 99175-8416', $settings['contact_whatsapp']);
        $this->assertSame('@JaderGabriel', $settings['telegram_handle']);
        // primary + embed específico → Calendar ID alinha ao src do embed
        $this->assertSame('example@group.calendar.google.com', $settings['google_calendar_id']);
        $this->assertSame('1', $settings['google_auto_sync']);
        $this->assertSame(
            'https://calendar.google.com/calendar/embed?src=example%40group.calendar.google.com',
            app(SettingService::class)->calendarSrc()
        );
    }

    public function test_search_suggests_modules_and_submodules(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-admin-search', false)
            ->assertSee('data-admin-search-list', false)
            ->assertSee('Buscar módulo, contato, empresa', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'agen']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Agenda',
                'type' => 'página',
                'subtitle' => 'Entrega',
            ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'jir']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Jirau',
                'type' => 'módulo',
            ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'config']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Configurações',
                'subtitle' => 'Sistema',
            ]);
    }
}
