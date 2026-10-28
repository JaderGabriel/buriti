<?php

namespace Tests\Feature\Console;

use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_prune_logs_removes_old_rows_and_keeps_recent(): void
    {
        $user = User::factory()->create();

        $oldLogin = LoginActivity::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'successful' => false,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now()->subDays(120),
        ]);
        $recentLogin = LoginActivity::query()->create([
            'user_id' => $user->id,
            'email' => $user->email,
            'successful' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now()->subDays(10),
        ]);
        $oldAudit = AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'auth.login.failed',
            'properties' => ['summary' => 'antigo'],
            'created_at' => now()->subDays(120),
        ]);
        $recentAudit = AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'auth.login.success',
            'properties' => ['summary' => 'recente'],
            'created_at' => now()->subDays(3),
        ]);

        $this->artisan('security:prune-logs', ['--days' => 90])
            ->expectsOutputToContain('Removidas 1 tentativas de login e 1 auditorias')
            ->assertSuccessful();

        $this->assertDatabaseMissing('login_activities', ['id' => $oldLogin->id]);
        $this->assertDatabaseHas('login_activities', ['id' => $recentLogin->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $recentAudit->id]);
    }

    public function test_telegram_configure_fails_without_token(): void
    {
        config(['services.telegram.bot_token' => '']);

        $this->artisan('telegram:configure', ['--skip-webhook' => true, '--skip-name' => true])
            ->expectsOutputToContain('TELEGRAM_BOT_TOKEN')
            ->assertFailed();
    }

    public function test_telegram_configure_registers_commands_when_token_is_set(): void
    {
        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'username' => 'BuritiCrmBot',
                    'url' => 'https://buriti.test/webhooks/telegram/secret-test',
                ],
            ]),
        ]);

        $this->artisan('telegram:configure', ['--skip-webhook' => true, '--skip-name' => true])
            ->expectsOutputToContain('BuritiCrmBot')
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'setMyCommands'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
    }

    public function test_telegram_set_webhook_registers_https_url(): void
    {
        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => [
                    'username' => 'BuritiCrmBot',
                    'url' => 'https://buriti.test/webhooks/telegram/secret-test',
                ],
            ]),
        ]);

        $this->artisan('telegram:set-webhook')
            ->expectsOutputToContain('Webhook registado com sucesso')
            ->assertSuccessful();

        Http::assertSent(function ($request) {
            $data = $request->data();

            return str_contains($request->url(), 'setWebhook')
                && ($data['url'] ?? null) === 'https://buriti.test/webhooks/telegram/secret-test'
                && ($data['secret_token'] ?? null) === 'secret-test';
        });
    }

    public function test_telegram_set_webhook_fails_without_secret(): void
    {
        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.webhook_secret' => '',
        ]);

        $this->artisan('telegram:set-webhook')
            ->expectsOutputToContain('TELEGRAM_WEBHOOK_SECRET')
            ->assertFailed();
    }

    public function test_tasks_telegram_reminders_command_wraps_the_service(): void
    {
        config(['services.telegram.bot_token' => '123456:TESTTOKEN']);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);

        $this->artisan('tasks:telegram-reminders')
            ->expectsOutputToContain('Lembretes:')
            ->assertSuccessful();
    }
}
