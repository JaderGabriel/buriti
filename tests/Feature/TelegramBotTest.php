<?php

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Enums\OpportunityStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@buriti.test',
            'username' => 'adminbot',
            'password' => 'password',
            'is_admin' => true,
            'is_active' => true,
        ]);

        app(SettingService::class)->putMany([
            'telegram_notify_chat_id' => '999001',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    private function loginAdmin(string $chatId = '999001'): void
    {
        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'message_id' => 1001,
                'chat' => ['id' => (int) $chatId],
                'text' => '/login admin@buriti.test | password',
            ],
        ])->assertOk();

        $this->assertSame($chatId, $this->admin->fresh()->telegram_chat_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteMessage')
            && ($request->data()['message_id'] ?? null) == 1001);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->postJson('/webhooks/telegram/wrong-secret', [
            'message' => ['chat' => ['id' => 999001], 'text' => '/ajuda'],
        ])->assertNotFound();
    }

    public function test_unauthenticated_chat_cannot_run_crm_commands(): void
    {
        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/contato Intruso | hack@x.com',
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('contacts', ['email' => 'hack@x.com']);
        Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'administradores'));
    }

    public function test_non_admin_cannot_login(): void
    {
        User::factory()->withoutAdminAccess()->create([
            'email' => 'user@buriti.test',
            'password' => 'password',
        ]);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'message_id' => 2002,
                'chat' => ['id' => 555],
                'text' => '/login user@buriti.test | password',
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('users', [
            'email' => 'user@buriti.test',
            'telegram_chat_id' => '555',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteMessage')
            && ($request->data()['message_id'] ?? null) == 2002);
    }

    public function test_admin_can_login_and_use_crm(): void
    {
        $this->loginAdmin();

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/contato Ana Silva | ana@empresa.com | 11999990000 | Acme',
            ],
        ])->assertOk();

        $contact = Contact::query()->where('email', 'ana@empresa.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame(ContactSource::Telegram, $contact->source);
        $this->assertSame('Ana Silva', $contact->name);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/oportunidade ana@empresa.com | Site institucional | qualified | 15000',
            ],
        ])->assertOk();

        $opportunity = Opportunity::query()->where('title', 'Site institucional')->first();
        $this->assertNotNull($opportunity);
        $this->assertSame(OpportunityStage::Qualified, $opportunity->stage);
        $this->assertSame($contact->id, $opportunity->contact_id);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/projeto Portal Acme | Redesign completo | Web',
            ],
        ])->assertOk();

        $project = Project::query()->where('name', 'Portal Acme')->first();
        $this->assertNotNull($project);
        $this->assertSame(ProjectStatus::Active, $project->status);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/tarefa Kickoff | {$project->id} | ana@empresa.com | high | todo",
            ],
        ])->assertOk();

        $task = Task::query()->where('title', 'Kickoff')->first();
        $this->assertNotNull($task);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame(TaskStatus::Todo, $task->status);
    }

    public function test_admin_can_logout(): void
    {
        $this->loginAdmin();

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/logout',
            ],
        ])->assertOk();

        $this->assertNull($this->admin->fresh()->telegram_chat_id);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/contatos',
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->data()['text'] ?? '', 'administradores'));
    }

    public function test_website_form_notifies_telegram(): void
    {
        $this->loginAdmin();

        $this->post(route('contact.store'), [
            'name' => 'Lead Site',
            'email' => 'lead@site.com',
            'phone_country' => 'BR',
            'phone_number' => '11988887777',
            'preferred_channel' => 'email',
            'company' => 'Site Co',
            'subject' => 'Preciso de proposta',
            'message' => 'Olá, quero um orçamento.',
            'privacy_consent' => '1',
            'website' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'lead@site.com',
            'subject' => 'Preciso de proposta',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $data = $request->data();

            return ($data['chat_id'] ?? null) == '999001'
                && str_contains((string) ($data['text'] ?? ''), 'Nova mensagem do site')
                && str_contains((string) ($data['text'] ?? ''), 'Lead Site');
        });
    }

    public function test_bot_lists_shows_updates_and_deletes_crm_records(): void
    {
        $this->loginAdmin();

        $contact = Contact::factory()->create([
            'name' => 'Ana Silva',
            'email' => 'ana@empresa.com',
        ]);
        $project = Project::factory()->create(['name' => 'Portal Acme']);
        $task = Task::factory()->create([
            'title' => 'Kickoff',
            'project_id' => $project->id,
            'contact_id' => $contact->id,
            'status' => TaskStatus::Todo,
            'priority' => TaskPriority::Medium,
        ]);
        $opportunity = Opportunity::factory()->create([
            'contact_id' => $contact->id,
            'title' => 'Site institucional',
            'stage' => OpportunityStage::Lead,
        ]);
        $message = ContactMessage::factory()->create([
            'name' => 'Lead Site',
            'email' => 'lead@site.com',
            'subject' => 'Proposta',
            'read_at' => null,
        ]);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => ['chat' => ['id' => 999001], 'text' => '/contatos'],
        ])->assertOk();

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/contato set {$contact->id} | Ana Atualizada | . | . | . | active",
            ],
        ])->assertOk();
        $this->assertSame('Ana Atualizada', $contact->fresh()->name);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/tarefa set {$task->id} | Kickoff OK | . | . | high | doing",
            ],
        ])->assertOk();
        $task->refresh();
        $this->assertSame('Kickoff OK', $task->title);
        $this->assertSame(TaskPriority::High, $task->priority);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/oportunidade set {$opportunity->id} | . | Site Plus | qualified | 20000",
            ],
        ])->assertOk();
        $this->assertSame('Site Plus', $opportunity->fresh()->title);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => ['chat' => ['id' => 999001], 'text' => "/mensagem lida {$message->id}"],
        ])->assertOk();
        $this->assertNotNull($message->fresh()->read_at);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => ['chat' => ['id' => 999001], 'text' => "/projeto del {$project->id} ok"],
        ])->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => ['chat' => ['id' => 999001], 'text' => "/tarefa del {$task->id} ok"],
        ])->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_admin_can_save_telegram_notify_setting(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.integrations.update'), [
            'telegram_notify_chat_id' => '42',
        ])->assertRedirect(route('admin.integrations.edit'));

        $settings = app(SettingService::class)->all();
        $this->assertSame('42', $settings['telegram_notify_chat_id']);

        $this->actingAs($admin)
            ->get(route('admin.integrations.edit'))
            ->assertOk()
            ->assertSee('Telegram Bot', false)
            ->assertSee('/login', false);
    }
}
