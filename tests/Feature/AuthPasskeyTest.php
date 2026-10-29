<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebauthnCredential;
use App\Services\Auth\PasskeyService;
use App\Services\Auth\PasswordTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AuthPasskeyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeTelegramApi();

        $this->admin = User::factory()->telegramLinked()->create([
            'email' => 'passkey@buriti.test',
            'username' => 'passkeyadmin',
            'password' => 'secret123',
        ]);
    }

    public function test_login_page_does_not_offer_passkey_setup_for_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Entrar com chave de acesso', false)
            ->assertDontSee('data-passkey-start', false)
            ->assertSee('cadastra-se no perfil', false)
            ->assertSee('data-passkey-login', false)
            ->assertSee('autocomplete="username webauthn"', false)
            ->assertSee('name="password"', false);
    }

    public function test_passkey_options_return_a_challenge(): void
    {
        $this->postJson(route('login.passkey.options'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'publicKey' => ['challenge', 'rpId']]);

        $this->assertTrue(session()->has(PasskeyService::LOGIN_SESSION));
    }

    public function test_unknown_passkey_does_not_authenticate(): void
    {
        $this->postJson(route('login.passkey.options'))->assertOk();

        $this->postJson(route('login.passkey'), [
            'id' => 'chave-inexistente',
            'clientDataJSON' => 'e30',
            'authenticatorData' => 'e30',
            'signature' => 'e30',
        ])->assertStatus(422)->assertJsonPath('ok', false);

        $this->assertGuest();
    }

    public function test_passkey_login_skips_two_factor_code(): void
    {
        $this->mock(PasskeyService::class, function (MockInterface $mock) {
            $mock->shouldReceive('assert')->once()->andReturn($this->admin);
        });

        $response = $this->postJson(route('login.passkey'), ['id' => 'credencial-ok']);
        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertStringContainsString('/admin', (string) $response->json('redirect'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(session()->has(PasswordTwoFactorService::SESSION_KEY));
    }

    public function test_password_login_still_requires_code_when_passkey_exists(): void
    {
        WebauthnCredential::query()->create([
            'user_id' => $this->admin->id,
            'credential_id' => 'credencial-local',
            'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----',
            'sign_count' => 0,
            'name' => 'iPhone',
        ]);

        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'secret123',
        ])->assertRedirect(route('login.two-factor'));

        $this->assertGuest();
        $this->assertTrue(session()->has(PasswordTwoFactorService::SESSION_KEY));
    }

    public function test_profile_shows_passkey_controls(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('Chave de acesso · iPhone / Safari', false)
            ->assertSee('Adicionar chave de acesso', false)
            ->assertSee('só se cadastra aqui', false)
            ->assertSee('data-passkey-register', false);
    }

    public function test_logged_in_user_can_start_passkey_with_password(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.profile.passkey.options'), [
                'current_password' => 'secret123',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'publicKey' => ['challenge', 'rp', 'user']]);

        $this->assertTrue(session()->has(PasskeyService::REGISTER_SESSION));
    }

    public function test_cannot_start_passkey_without_current_password(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('admin.profile.passkey.options'))
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('errors.current_password.0', 'Informe a senha atual para criar a chave de acesso.');
    }

    public function test_guest_cannot_register_passkey(): void
    {
        $this->postJson(route('admin.profile.passkey.options'), [
            'current_password' => 'secret123',
        ])->assertRedirect(route('login'));
    }

    public function test_can_remove_passkey_with_current_password(): void
    {
        $credential = WebauthnCredential::query()->create([
            'user_id' => $this->admin->id,
            'credential_id' => 'para-remover',
            'public_key' => '-----BEGIN PUBLIC KEY-----\nMIIB\n-----END PUBLIC KEY-----',
            'sign_count' => 1,
            'name' => 'iPhone',
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.profile.edit'))
            ->delete(route('admin.profile.passkey.destroy', $credential), [
                'current_password' => 'secret123',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('webauthn_credentials', ['id' => $credential->id]);
    }
}
