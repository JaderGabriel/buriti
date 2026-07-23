<?php

namespace Tests\Feature;

use App\Enums\ContactSource;
use App\Enums\CrmActivityType;
use App\Enums\OpportunityStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
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

    /** @param  array<string, mixed>  $payload */
    private function postWebhook(array $payload, string $secret = 'secret-test')
    {
        return $this->postJson(
            route('webhooks.telegram', ['secret' => $secret]),
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => 'secret-test'],
        );
    }

    private function loginAdmin(string $chatId = '999001'): void
    {
        $this->postWebhook([
            'message' => [
                'message_id' => 1001,
                'chat' => ['id' => (int) $chatId],
                'text' => '/login admin@buriti.test | password',
            ],
        ])->assertOk();

        $this->assertSame($chatId, $this->admin->fresh()->telegram_chat_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'deleteMessage')
            && ($request['message_id'] ?? $request->data()['message_id'] ?? null) == 1001);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->postJson('/webhooks/telegram/wrong-secret', [
            'message' => ['chat' => ['id' => 999001], 'text' => '/ajuda'],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'secret-test',
        ])->assertNotFound();
    }

    public function test_webhook_rejects_missing_secret_header(): void
    {
        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => ['chat' => ['id' => 999001], 'text' => '/ajuda'],
        ])->assertForbidden();
    }

    public function test_unauthenticated_chat_cannot_run_crm_commands(): void
    {
        $this->postWebhook([
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

        $this->postWebhook([
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

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/contato Ana Silva | ana@empresa.com | 11999990000 | Acme',
            ],
        ])->assertOk();

        $contact = Contact::query()->where('email', 'ana@empresa.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame(ContactSource::Telegram, $contact->source);
        $this->assertSame('Ana Silva', $contact->name);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/oportunidade ana@empresa.com | Site institucional | qualified | 15000',
            ],
        ])->assertOk();

        $opportunity = Opportunity::query()->where('title', 'Site institucional')->first();
        $this->assertNotNull($opportunity);
        $this->assertSame(OpportunityStage::Qualified, $opportunity->stage);
        $this->assertSame($contact->id, $opportunity->contact_id);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/projeto Portal Acme | Redesign completo | Web',
            ],
        ])->assertOk();

        $project = Project::query()->where('name', 'Portal Acme')->first();
        $this->assertNotNull($project);
        $this->assertSame(ProjectStatus::Active, $project->status);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/tarefa Kickoff | {$project->id} | ana@empresa.com | high | todo",
            ],
        ])->assertOk();

        $task = Task::query()->where('title', 'Kickoff')->first();
        $this->assertNotNull($task);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertSame(TaskStatus::Todo, $task->status);

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Tarefa criada na agenda')
                && str_contains($text, 'Kickoff')
                && str_contains($text, 'Abrir na agenda do CRM');
        });
    }

    public function test_admin_can_logout(): void
    {
        $this->loginAdmin();

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/logout',
            ],
        ])->assertOk();

        $this->assertNull($this->admin->fresh()->telegram_chat_id);

        $this->postWebhook([
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

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => '/contatos'],
        ])->assertOk();

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/contato set {$contact->id} | Ana Atualizada | . | . | . | active",
            ],
        ])->assertOk();
        $this->assertSame('Ana Atualizada', $contact->fresh()->name);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/tarefa set {$task->id} | Kickoff OK | . | . | high | doing",
            ],
        ])->assertOk();
        $task->refresh();
        $this->assertSame('Kickoff OK', $task->title);
        $this->assertSame(TaskPriority::High, $task->priority);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/oportunidade set {$opportunity->id} | . | Site Plus | qualified | 20000",
            ],
        ])->assertOk();
        $this->assertSame('Site Plus', $opportunity->fresh()->title);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/mensagem lida {$message->id}"],
        ])->assertOk();
        $this->assertNotNull($message->fresh()->read_at);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/projeto del {$project->id} ok"],
        ])->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/tarefa del {$task->id} ok"],
        ])->assertOk();
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_tarefas_numeric_argument_opens_task_by_id_not_list_limit(): void
    {
        $this->loginAdmin();

        $older = Task::factory()->create([
            'title' => 'Kickoff e fundação da plataforma',
            'status' => TaskStatus::Done,
            'due_at' => now()->subMonths(3),
        ]);
        $wanted = Task::factory()->create([
            'title' => 'Reunião de Alinhamento',
            'status' => TaskStatus::Done,
            'due_at' => now()->subHour(),
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/tarefas {$wanted->id}"],
        ])->assertOk();

        Http::assertSent(function ($request) use ($wanted, $older) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, '#'.$wanted->id)
                && str_contains($text, 'Reunião de Alinhamento')
                && ! str_contains($text, 'Kickoff e fundação da plataforma')
                && ! str_contains($text, 'Agenda · Tarefas')
                && $older->id !== $wanted->id;
        });
    }

    public function test_tarefas_limite_still_lists_with_custom_limit(): void
    {
        $this->loginAdmin();

        Task::factory()->count(3)->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->addDay(),
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => '/tarefas limite 2'],
        ])->assertOk();

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Em aberto')
                && str_contains($text, 'Concluídas recentes')
                && str_contains($text, '/tarefas 12');
        });
    }

    public function test_tarefas_lists_open_upcoming_and_recent_done(): void
    {
        $this->loginAdmin();

        $today = Task::factory()->create([
            'title' => 'Standup de hoje',
            'status' => TaskStatus::Todo,
            'due_at' => now()->setTime(10, 0),
        ]);
        $tomorrow = Task::factory()->create([
            'title' => 'Call de amanhã',
            'status' => TaskStatus::Doing,
            'due_at' => now()->addDay()->setTime(15, 30),
        ]);
        $farAway = Task::factory()->create([
            'title' => 'Planeamento longínquo',
            'status' => TaskStatus::Todo,
            'due_at' => now()->addDays(20)->setTime(9, 0),
        ]);
        $recentDone = Task::factory()->create([
            'title' => 'Entrega fechada ontem',
            'status' => TaskStatus::Done,
            'due_at' => now()->subDay()->setTime(18, 0),
        ]);
        $oldDone = Task::factory()->create([
            'title' => 'Kickoff antigo',
            'status' => TaskStatus::Done,
            'due_at' => now()->subMonths(2),
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => '/tarefas'],
        ])->assertOk();

        Http::assertSent(function ($request) use ($today, $tomorrow, $farAway, $recentDone, $oldDone) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }

            $text = (string) ($request->data()['text'] ?? '');
            $openPos = strpos($text, 'Em aberto');
            $donePos = strpos($text, 'Concluídas recentes');
            $todayPos = strpos($text, 'Standup de hoje');
            $recentPos = strpos($text, 'Entrega fechada ontem');
            $oldPos = strpos($text, 'Kickoff antigo');

            return $openPos !== false
                && $donePos !== false
                && $todayPos !== false
                && $recentPos !== false
                && $oldPos !== false
                && $openPos < $todayPos
                && $todayPos < $donePos
                && $donePos < $recentPos
                && $recentPos < $oldPos
                && str_contains($text, 'Call de amanhã')
                && str_contains($text, '#'.$today->id)
                && str_contains($text, '#'.$tomorrow->id)
                && str_contains($text, '#'.$recentDone->id)
                && str_contains($text, '#'.$oldDone->id)
                && ! str_contains($text, 'Planeamento longínquo')
                && $farAway->id > 0;
        });
    }

    public function test_bot_can_list_show_create_update_and_delete_activities(): void
    {
        $this->loginAdmin();

        $contact = Contact::factory()->create([
            'name' => 'Cliente Atividade',
            'email' => 'atividade@empresa.com',
        ]);
        $task = Task::factory()->create([
            'title' => 'Follow-up comercial',
            'status' => TaskStatus::Todo,
            'contact_id' => $contact->id,
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/atividade add {$contact->id} | call | Ligação de alinhamento | Falámos do escopo | {$task->id}",
            ],
        ])->assertOk();

        $activity = CrmActivity::query()->where('contact_id', $contact->id)->latest('id')->first();
        $this->assertNotNull($activity);
        $this->assertSame(CrmActivityType::Call, $activity->type);
        $this->assertSame('Ligação de alinhamento', $activity->subject);
        $this->assertSame($task->id, $activity->task_id);
        $this->assertSame(TaskStatus::Todo, $task->fresh()->status);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/atividade add {$contact->id} | note | Follow-up | Enviado resumo | {$task->id} | . | concluir",
            ],
        ])->assertOk();

        $this->assertSame(TaskStatus::Done, $task->fresh()->status);

        Http::assertSent(function ($request) use ($activity) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Atividade registada')
                && str_contains($text, '#'.$activity->id)
                && str_contains($text, 'Ligação de alinhamento');
        });

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => "/atividade set {$activity->id} | . | meeting | Reunião de fecho | . | . | .",
            ],
        ])->assertOk();

        $activity->refresh();
        $this->assertSame(CrmActivityType::Meeting, $activity->type);
        $this->assertSame('Reunião de fecho', $activity->subject);

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => '/atividades'],
        ])->assertOk();

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/atividades contato {$contact->id}"],
        ])->assertOk();

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/atividade {$activity->id}"],
        ])->assertOk();

        Http::assertSent(function ($request) use ($activity) {
            if (! str_contains($request->url(), 'sendMessage')) {
                return false;
            }
            $text = (string) ($request->data()['text'] ?? '');

            return str_contains($text, 'Atividade CRM')
                || str_contains($text, 'Atividades')
                || str_contains($text, '#'.$activity->id);
        });

        $this->postWebhook([
            'message' => ['chat' => ['id' => 999001], 'text' => "/atividade del {$activity->id} ok"],
        ])->assertOk();

        $this->assertDatabaseMissing('crm_activities', ['id' => $activity->id]);
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

    public function test_admin_can_generate_share_card(): void
    {
        $this->loginAdmin();

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/card Acme Educacional',
            ],
        ])->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendPhoto'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Card pronto'));

        $card = app(\App\Services\Telegram\TelegramShareCardService::class)->build('Acme Educacional');
        $this->assertFileExists($card['path']);
        $this->assertStringContainsString('Acme Educacional', $card['caption']);
        $this->assertStringContainsString('BURI-TI', $card['caption']);
        $this->assertNotEmpty($card['reply_markup']['inline_keyboard'] ?? []);
        @unlink($card['path']);
    }
}
