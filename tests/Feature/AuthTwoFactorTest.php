<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\PasswordTwoFactorService;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTelegramApi();
        config(['app.url' => 'https://buriti.test']);
        URL::forceRootUrl('https://buriti.test');
        URL::forceScheme('https');

        $this->admin = User::factory()->telegramLinked()->create([
            'email' => 'admin@buriti.test',
            'username' => 'admin2fa',
            'password' => 'secret123',
        ]);
    }

    public function test_password_login_sends_telegram_code_and_does_not_authenticate_yet(): void
    {
        $response = $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login.two-factor'));
        $this->assertGuest();
        $this->assertTrue(session()->has(PasswordTwoFactorService::SESSION_KEY));

        Http::assertSent(function ($request) {
            $text = (string) ($request->data()['text'] ?? '');
            $markup = $request->data()['reply_markup'] ?? [];
            $buttonUrl = $markup['inline_keyboard'][0][0]['url'] ?? '';

            return str_contains($request->url(), 'sendMessage')
                && str_contains($text, '<code>')
                && str_contains($text, 'is your BURI-TI verification code.')
                && str_contains($buttonUrl, '/admin/login/2fa')
                && ! str_contains($buttonUrl, 'code=');
        });
    }

    public function test_two_factor_page_shows_six_digit_keypad(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $this->get(route('login.two-factor'))
            ->assertOk()
            ->assertSee('Digite o código', false)
            ->assertSee('data-otp-form', false)
            ->assertSee('data-otp-keypad', false)
            ->assertSee('data-otp-cell', false)
            ->assertSee('autocomplete="one-time-code"', false)
            ->assertSee('id="one-time-code"', false)
            ->assertSee('enterkeyhint="done"', false);
    }

    public function test_two_factor_page_ignores_code_in_query_string(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $code = $this->lastTelegramTwoFactorCode();

        $this->get(route('login.two-factor', ['code' => $code]))
            ->assertOk()
            ->assertDontSee('value="'.$code.'"', false)
            ->assertDontSee('data-otp-prefill="'.$code.'"', false);
    }

    public function test_valid_telegram_code_completes_password_login(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
            'remember' => '1',
        ])->assertRedirect(route('login.two-factor'));

        $this->post(route('login.two-factor.confirm'), [
            'code' => $this->lastTelegramTwoFactorCode(),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);
        $this->assertDatabaseHas('login_activities', [
            'user_id' => $this->admin->id,
            'successful' => 1,
        ]);
    }

    public function test_username_password_login_also_requires_two_factor(): void
    {
        $this->post(route('login'), [
            'login' => 'admin2fa',
            'password' => 'secret123',
        ])->assertRedirect(route('login.two-factor'));

        $this->post(route('login.two-factor.confirm'), [
            'code' => $this->lastTelegramTwoFactorCode(),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_wrong_code_keeps_guest_and_allows_retry(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $this->from(route('login.two-factor'))
            ->post(route('login.two-factor.confirm'), ['code' => '000000'])
            ->assertRedirect(route('login.two-factor'))
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertTrue(session()->has(PasswordTwoFactorService::SESSION_KEY));
    }

    public function test_too_many_invalid_codes_lock_the_challenge(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        for ($i = 0; $i < PasswordTwoFactorService::MAX_ATTEMPTS; $i++) {
            $this->from(route('login.two-factor'))
                ->post(route('login.two-factor.confirm'), ['code' => '000000']);
        }

        $this->assertGuest();
        $this->assertFalse(session()->has(PasswordTwoFactorService::SESSION_KEY));
    }

    public function test_account_without_telegram_cannot_use_password_login(): void
    {
        $user = User::factory()->create([
            'email' => 'notelegram@buriti.test',
            'password' => 'secret123',
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'login' => $user->email,
                'password' => 'secret123',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_resend_sends_a_new_code(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $first = $this->lastTelegramTwoFactorCode();

        $this->travel(PasswordTwoFactorService::RESEND_SECONDS + 1)->seconds();

        $this->from(route('login.two-factor'))
            ->post(route('login.two-factor.resend'))
            ->assertRedirect(route('login.two-factor'))
            ->assertSessionHas('status');

        $second = $this->lastTelegramTwoFactorCode();
        $this->assertNotSame($first, $second);

        $this->post(route('login.two-factor.confirm'), ['code' => $first])
            ->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post(route('login.two-factor.confirm'), ['code' => $second])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_cancel_returns_to_login_without_session(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $this->post(route('login.two-factor.cancel'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse(session()->has(PasswordTwoFactorService::SESSION_KEY));
    }

    public function test_two_factor_page_without_challenge_redirects_to_login(): void
    {
        $this->get(route('login.two-factor'))
            ->assertRedirect(route('login'));
    }

    public function test_two_factor_query_without_challenge_redirects_to_login(): void
    {
        $this->followingRedirects()
            ->get(route('login.two-factor', ['code' => '123456']))
            ->assertSee('A verificação expirou', false)
            ->assertDontSee('mesmo Safari', false);
    }

    public function test_ios_two_factor_logs_in_without_telegram(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $user = User::factory()->iosTwoFactor($secret)->create([
            'email' => 'ios@buriti.test',
            'username' => 'iosadmin',
            'password' => 'secret123',
        ]);

        $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect(route('login.two-factor'));

        Http::assertNothingSent();
        $this->assertGuest();

        $this->get(route('login.two-factor'))
            ->assertOk()
            ->assertDontSee('Enviar pelo Telegram', false)
            ->assertDontSee('Reenviar código', false);

        $this->post(route('login.two-factor.confirm'), [
            'code' => $totp->at($secret, time()),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_ios_is_preferred_when_telegram_is_also_linked(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $this->admin->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ])->save();

        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('login.two-factor'));

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'sendMessage'));

        $this->get(route('login.two-factor'))
            ->assertOk()
            ->assertSee('Enviar pelo Telegram', false);

        $this->post(route('login.two-factor.confirm'), [
            'code' => $totp->at($secret, time()),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_telegram_remains_available_as_fallback_when_ios_is_linked(): void
    {
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $this->admin->forceFill([
            'totp_secret' => $secret,
            'totp_confirmed_at' => now(),
        ])->save();

        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ]);

        $this->from(route('login.two-factor'))
            ->post(route('login.two-factor.resend'))
            ->assertRedirect(route('login.two-factor'))
            ->assertSessionHas('status');

        $this->post(route('login.two-factor.confirm'), [
            'code' => $this->lastTelegramTwoFactorCode(),
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
    }
}
