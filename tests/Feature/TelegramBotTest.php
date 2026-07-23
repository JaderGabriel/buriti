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
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);

        app(SettingService::class)->putMany([
            'telegram_allowed_chat_ids' => '999001',
            'telegram_notify_chat_id' => '999001',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->postJson('/webhooks/telegram/wrong-secret', [
            'message' => ['chat' => ['id' => 999001], 'text' => '/ajuda'],
        ])->assertNotFound();
    }

    public function test_bot_creates_contact_opportunity_project_and_task(): void
    {
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
        $this->assertFalse($project->is_public);

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
        $this->assertSame($project->id, $task->project_id);
        $this->assertSame($contact->id, $task->contact_id);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    public function test_unauthorized_chat_cannot_create_records(): void
    {
        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 111],
                'text' => '/contato Intruso | hack@x.com',
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('contacts', ['email' => 'hack@x.com']);
    }

    public function test_website_form_notifies_telegram(): void
    {
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

    public function test_notify_chat_id_is_also_authorized_for_commands(): void
    {
        app(SettingService::class)->putMany([
            'telegram_allowed_chat_ids' => '',
            'telegram_notify_chat_id' => '555001',
        ]);

        $this->postJson(route('webhooks.telegram', ['secret' => 'secret-test']), [
            'message' => [
                'chat' => ['id' => 555001],
                'text' => '/contato Notify User | notify@empresa.com',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('contacts', ['email' => 'notify@empresa.com']);
    }

    public function test_admin_can_save_telegram_chat_settings(): void
    {
        $admin = \App\Models\User::factory()->create();

        $this->actingAs($admin)->put(route('admin.integrations.update'), [
            'telegram_allowed_chat_ids' => '42, 43',
            'telegram_notify_chat_id' => '42',
        ])->assertRedirect(route('admin.integrations.edit'));

        $settings = app(SettingService::class)->all();
        $this->assertSame('42, 43', $settings['telegram_allowed_chat_ids']);
        $this->assertSame('42', $settings['telegram_notify_chat_id']);

        $this->actingAs($admin)
            ->get(route('admin.integrations.edit'))
            ->assertOk()
            ->assertSee('Telegram Bot', false)
            ->assertSee('/contato', false);
    }
}
