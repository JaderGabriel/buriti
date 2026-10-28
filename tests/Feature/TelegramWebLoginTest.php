<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramWebAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebLoginTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => '123456:TESTTOKEN',
            'services.telegram.bot_username' => 'BuritiCrmBot',
            'services.telegram.webhook_secret' => 'secret-test',
            'app.url' => 'https://buriti.test',
        ]);

        $this->admin = User::factory()->create([
            'email' => 'admin@buriti.test',
            'username' => 'adminbot',
            'password' => 'password',
        ]);
        $this->admin->forceFill([
            'is_admin' => true,
            'is_active' => true,
            'telegram_chat_id' => '999001',
        ])->save();

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    private function postWebhook(array $payload)
    {
        return $this->postJson(
            route('webhooks.telegram', ['secret' => 'secret-test']),
            $payload,
            ['X-Telegram-Bot-Api-Secret-Token' => 'secret-test'],
        );
    }

    public function test_login_page_shows_telegram_challenge_only(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continuar com Telegram', false)
            ->assertSee('desafio temporário', false)
            ->assertDontSee('telegram-widget.js', false)
            ->assertDontSee('data-telegram-login="BuritiCrmBot"', false)
            ->assertSee('Entrar no painel', false);
    }

    public function test_telegram_challenge_can_be_started(): void
    {
        $response = $this->postJson(route('login.telegram.start'));

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['token', 'deep_link', 'status_url', 'complete_url']);

        $this->assertStringContainsString('weblogin_', $response->json('deep_link'));
        $this->assertTrue(session()->has(TelegramWebAuthService::SESSION_KEY));
    }

    public function test_bot_start_weblogin_approves_linked_admin(): void
    {
        $token = $this->postJson(route('login.telegram.start'))->json('token');

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/start weblogin_'.$token,
            ],
        ])->assertOk();

        $status = $this->getJson(route('login.telegram.status', ['token' => $token]))
            ->assertOk()
            ->json();
        $this->assertSame('ready', $status['status'] ?? null);
        $this->assertArrayNotHasKey('complete_url', $status);

        $this->get(route('login.telegram.complete', ['token' => $token]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_stolen_complete_url_does_not_login_another_browser(): void
    {
        $token = $this->postJson(route('login.telegram.start'))->json('token');

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/start weblogin_'.$token,
            ],
        ])->assertOk();

        $this->flushSession();

        $this->get(route('login.telegram.complete', ['token' => $token]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unlinked_chat_cannot_approve_web_login(): void
    {
        $token = $this->postJson(route('login.telegram.start'))->json('token');

        $this->postWebhook([
            'message' => [
                'chat' => ['id' => 555],
                'text' => '/start weblogin_'.$token,
            ],
        ])->assertOk();

        $status = $this->getJson(route('login.telegram.status', ['token' => $token]))
            ->assertOk()
            ->json();
        $this->assertSame('unlinked', $status['status'] ?? null);
        $this->assertArrayNotHasKey('complete_url', $status);

        $this->get(route('login.telegram.complete', ['token' => $token]))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_widget_callback_requires_a_challenge_started_in_this_session(): void
    {
        $authDate = now()->timestamp;
        $payload = [
            'id' => '999001',
            'first_name' => 'Admin',
            'username' => 'admin_tg',
            'auth_date' => (string) $authDate,
        ];

        $check = collect($payload)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->sortKeys()
            ->implode("\n");

        $hash = hash_hmac(
            'sha256',
            $check,
            hash('sha256', '123456:TESTTOKEN', true)
        );

        $this->get(route('login.telegram.callback', [...$payload, 'hash' => $hash]))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->postJson(route('login.telegram.start'))->assertOk();

        $this->get(route('login.telegram.callback', [...$payload, 'hash' => $hash]))
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_webhook_returns_ok_even_when_handler_throws(): void
    {
        $bot = \Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('configured')->andReturn(true);
        $bot->shouldReceive('handleUpdate')->once()->andThrow(new \RuntimeException('boom'));
        $bot->shouldReceive('notifyProcessingError')->once();
        $this->app->instance(TelegramBotService::class, $bot);

        $this->postWebhook([
            'update_id' => 1,
            'message' => [
                'chat' => ['id' => 999001],
                'text' => '/ajuda',
            ],
        ])->assertOk()->assertSee('ok');
    }

    public function test_widget_callback_rejects_invalid_hash(): void
    {
        $this->get(route('login.telegram.callback', [
            'id' => '999001',
            'auth_date' => (string) now()->timestamp,
            'hash' => 'deadbeef',
        ]))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
