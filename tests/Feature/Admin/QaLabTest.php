<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\QaRun;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class QaLabTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();

        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);
    }

    public function test_admin_can_open_qa_lab_and_search_finds_it(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.qa.index'))
            ->assertOk()
            ->assertSee('Testes', false)
            ->assertSee('Rodar suíte completa', false)
            ->assertSee('Suíte Jirau', false)
            ->assertSee('Saúde do comercial', false)
            ->assertSee('Saúde do Jirau', false)
            ->assertSee('Telas públicas (lote)', false)
            ->assertSee('Stack (DB, cache, disco)', false)
            ->assertSee('Ping no bot', false)
            ->assertSee('Reenviar último contato CRM', false)
            ->assertSee('Página inicial', false)
            ->assertSee('Relatórios', false)
            ->assertSee('>Testes</span>', false);

        $this->actingAs($this->admin)
            ->getJson(route('admin.search', ['q' => 'phpunit']))
            ->assertOk()
            ->assertJsonFragment([
                'title' => 'Testes',
                'type' => 'página',
                'subtitle' => 'Sistema',
            ]);
    }

    public function test_guest_and_staff_cannot_open_qa_lab(): void
    {
        $this->get(route('admin.qa.index'))
            ->assertRedirect(route('login'));

        $staff = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($staff)
            ->get(route('admin.qa.index'))
            ->assertRedirect(route('jirau.home'));

        $this->actingAs($staff)
            ->post(route('admin.qa.store'), ['command' => 'telegram.ping'])
            ->assertRedirect(route('jirau.home'));
    }

    public function test_admin_can_run_phpunit_suite_via_process_fake(): void
    {
        Process::fake(fn () => Process::result(
            output: "OK (343 tests, 2439 assertions)\n",
            exitCode: 0,
        ));

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'suite.phpunit'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertSame('suite.phpunit', $run->command);
        $this->assertStringContainsString('343 testes', (string) $run->summary);
        $this->assertSame(343, $run->meta['tests'] ?? null);

        $this->actingAs($this->admin)
            ->get(route('admin.qa.show', $run))
            ->assertOk()
            ->assertSee('Suíte OK', false)
            ->assertSee('OK (343 tests', false);

        Process::assertRan(function ($process) {
            $command = $process->command;

            if (! is_array($command)) {
                return false;
            }

            $line = implode(' ', $command);

            return str_contains($line, 'phpunit')
                && ! str_contains($line, 'php-fpm')
                && ($process->environment['DB_CONNECTION'] ?? null) === 'sqlite'
                && ($process->environment['DB_DATABASE'] ?? null) === ':memory:'
                && ($process->environment['APP_ENV'] ?? null) === 'testing'
                && ($process->environment['DB_PASSWORD'] ?? null) === '';
        });
    }

    public function test_suite_without_phpunit_output_is_reported_as_not_run(): void
    {
        Process::fake(fn () => Process::result(
            output: "No tests executed!\n",
            errorOutput: 'Could not read phpunit.xml',
            exitCode: 1,
        ));

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'suite.phpunit'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->status === QaRun::STATUS_FAILED);
        $this->assertStringContainsString('não rodou', (string) $run->summary);
        $this->assertStringContainsString('Could not read phpunit.xml', (string) $run->output);
        $this->assertSame(0, $run->meta['tests'] ?? null);
    }

    public function test_telegram_ping_sends_to_notify_chat(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        app(SettingService::class)->putMany([
            'telegram_notify_chat_id' => '999001',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'telegram.ping'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertStringContainsString('999001', (string) $run->output);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) ($request->data()['text'] ?? ''), 'módulo Testes'));
    }

    public function test_telegram_resend_last_notifies_latest_message(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        app(SettingService::class)->putMany([
            'telegram_notify_chat_id' => '999001',
        ]);

        ContactMessage::factory()->create(['name' => 'Antiga', 'subject' => 'Velha']);
        $latest = ContactMessage::factory()->create([
            'name' => 'Carla Site',
            'subject' => 'Orçamento institucional',
            'phone' => '+55 (51) 98191-6277',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'telegram.resend_last'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertSame($latest->id, $run->meta['message_id'] ?? null);
        $this->assertStringContainsString('Carla Site', (string) $run->summary);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && str_contains((string) ($request->data()['text'] ?? ''), 'Carla Site'));
    }

    public function test_telegram_webhook_probe_redacts_secret(): void
    {
        Http::fake([
            'api.telegram.org/*/getMe' => Http::response([
                'ok' => true,
                'result' => ['username' => 'BuritiCrmBot'],
            ]),
            'api.telegram.org/*/getWebhookInfo' => Http::response([
                'ok' => true,
                'result' => [
                    'url' => 'https://buriti.test/webhooks/telegram/secret-test',
                    'last_error_message' => '',
                ],
            ]),
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'telegram.webhook'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertStringContainsString('@BuritiCrmBot', (string) $run->summary);
        $this->assertStringContainsString('***', (string) $run->output);
        $this->assertStringNotContainsString('secret-test', (string) $run->output);
    }

    public function test_health_probes_record_http_status(): void
    {
        Http::fake([
            '*' => Http::response('<html>home</html>', 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'health.home'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertSame(200, $run->meta['status'] ?? null);
        $this->assertStringContainsString('HTTP 200', (string) $run->output);
    }

    public function test_second_suite_is_blocked_while_one_is_running(): void
    {
        QaRun::query()->create([
            'user_id' => $this->admin->id,
            'kind' => 'suite',
            'command' => 'suite.phpunit',
            'status' => QaRun::STATUS_RUNNING,
            'summary' => 'Rodar suíte completa',
            'started_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.qa.index'))
            ->post(route('admin.qa.store'), ['command' => 'suite.phpunit'])
            ->assertRedirect(route('admin.qa.index'))
            ->assertSessionHas('error');

        $this->assertSame(1, QaRun::query()->count());
    }

    public function test_unknown_command_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.qa.index'))
            ->post(route('admin.qa.store'), ['command' => 'rm.rf'])
            ->assertRedirect(route('admin.qa.index'))
            ->assertSessionHasErrors('command');
    }

    public function test_admin_suite_slice_passes_phpunit_path(): void
    {
        Process::fake(fn () => Process::result(
            output: "OK (40 tests, 200 assertions)\n",
            exitCode: 0,
        ));

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'suite.admin'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertSame('suite.admin', $run->command);

        Process::assertRan(function ($process) {
            $command = $process->command;

            return is_array($command)
                && in_array('tests/Feature/Admin', $command, true)
                && ($process->environment['DB_CONNECTION'] ?? null) === 'sqlite';
        });
    }

    public function test_module_health_counts_crm_records(): void
    {
        Company::factory()->create(['name' => 'Acme QA']);
        Contact::factory()->create();
        ContactMessage::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'crm.health'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertStringContainsString('Empresas: 1', (string) $run->output);
        $this->assertStringContainsString('rota admin.contacts.index', (string) $run->output);
        $this->actingAs($this->admin)
            ->get(route('admin.qa.show', $run))
            ->assertOk()
            ->assertSee('Checagens', false)
            ->assertSee('Rota admin.messages.index', false);
    }

    public function test_public_pack_and_ops_and_migrations_pass(): void
    {
        Http::fake([
            '*' => Http::response('<html>ok</html>', 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'site.pack'])
            ->assertRedirect();

        $pack = QaRun::query()->latest('id')->first();
        $this->assertNotNull($pack);
        $this->assertTrue($pack->isPassed());
        $this->assertSame(5, $pack->meta['total'] ?? null);
        $this->assertStringContainsString('Privacidade', (string) $pack->output);

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'ops.stack'])
            ->assertRedirect();

        $ops = QaRun::query()->latest('id')->first();
        $this->assertNotNull($ops);
        $this->assertTrue($ops->isPassed());
        $this->assertStringContainsString('base', mb_strtolower((string) $ops->output));

        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'ops.migrations'])
            ->assertRedirect();

        $migrations = QaRun::query()->latest('id')->first();
        $this->assertNotNull($migrations);
        $this->assertTrue($migrations->isPassed());
        $this->assertStringContainsString('em dia', (string) $migrations->summary);
    }

    public function test_google_status_reports_integration_level(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'ops.google'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertIsInt($run->meta['level'] ?? null);
        $this->assertGreaterThanOrEqual(0, (int) $run->meta['level']);
        $this->assertArrayHasKey('oauth_app', $run->meta);
        $this->assertStringContainsString('Google Agenda', (string) $run->summary);
    }

    public function test_jirau_health_lists_board_routes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.qa.store'), ['command' => 'jirau.health'])
            ->assertRedirect();

        $run = QaRun::query()->first();
        $this->assertNotNull($run);
        $this->assertTrue($run->isPassed());
        $this->assertStringContainsString('rota jirau.home', (string) $run->output);
        $this->assertStringContainsString('Quadros: 0', (string) $run->output);
    }
}
