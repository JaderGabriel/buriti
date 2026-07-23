<?php

namespace Tests\Feature\Admin;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'email' => 'admin@buriti.dev.br',
            'is_admin' => true,
        ]);
    }

    public function test_users_form_shows_random_password_generator(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Gerar senha aleatória', false)
            ->assertSee('data-password-generator', false)
            ->assertSee('data-password-toggle', false)
            ->assertSee('Copiar', false);

        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Criar usuário', false)
            ->assertSee('Editar', false);

        $other = User::factory()->create(['name' => 'Outro User']);
        $this->actingAs($this->admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Desativar', false)
            ->assertSee('Outro User', false);
    }

    public function test_admin_can_deactivate_and_reactivate_user(): void
    {
        $user = User::factory()->create([
            'email' => 'outro@buriti.dev.br',
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($user->fresh()->is_active);

        auth()->logout();

        $this->post('/admin/login', [
            'login' => 'outro@buriti.dev.br',
            'password' => 'password',
        ])->assertSessionHasErrors('login');

        $this->actingAs($this->admin)
            ->patch(route('admin.users.toggle-active', $user))
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_sidebar_has_users_not_profile_nav(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Usuários', $html);
        $this->assertStringContainsString('Integrações', $html);
        $this->assertStringContainsString('Sistema', $html);
        $this->assertStringContainsString('Comercial', $html);
        $this->assertStringNotContainsString('Segurança / Perfil', $html);
        $this->assertStringNotContainsString('Meu perfil', $html);
        $this->assertStringNotContainsString('CRM / Contatos', $html);
    }

    public function test_admin_can_create_user_with_avatar(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'Novo Admin',
            'username' => 'novoadmin',
            'email' => 'novo@buriti.dev.br',
            'password' => 'Senha1234',
            'password_confirmation' => 'Senha1234',
            'is_admin' => '1',
            'avatar' => UploadedFile::fake()->image('avatar.jpg'),
        ])->assertRedirect(route('admin.users.index'));

        $user = User::query()->where('email', 'novo@buriti.dev.br')->first();
        $this->assertNotNull($user);
        $this->assertSame('novoadmin', $user->username);
        $this->assertTrue($user->is_admin);
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }

    public function test_admin_can_update_profile_and_password(): void
    {
        $this->actingAs($this->admin)->put(route('admin.profile.update'), [
            'name' => 'Jader Atualizado',
            'username' => $this->admin->username,
            'email' => $this->admin->email,
            'current_password' => 'password',
            'password' => 'NovaSenha99',
            'password_confirmation' => 'NovaSenha99',
        ])->assertRedirect(route('admin.profile.edit'));

        $this->admin->refresh();
        $this->assertSame('Jader Atualizado', $this->admin->name);
        $this->assertTrue(Hash::check('NovaSenha99', $this->admin->password));
    }

    public function test_admin_can_update_avatar_separately(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)->put(route('admin.profile.avatar'), [
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])->assertRedirect(route('admin.profile.edit'));

        $this->admin->refresh();
        $this->assertNotNull($this->admin->avatar_path);
        Storage::disk('public')->assertExists($this->admin->avatar_path);

        $this->actingAs($this->admin)->put(route('admin.users.avatar', $this->admin), [
            'avatar' => UploadedFile::fake()->image('user.jpg'),
        ])->assertRedirect(route('admin.users.edit', $this->admin));

        $this->admin->refresh();
        $this->assertNotNull($this->admin->avatar_path);
        Storage::disk('public')->assertExists($this->admin->avatar_path);

        $edit = $this->actingAs($this->admin)
            ->get(route('admin.users.edit', $this->admin))
            ->assertOk();

        $edit->assertSee('Salvar foto', false);
        $edit->assertSee('Salvar dados da conta', false);
        $edit->assertSee('Só atualiza a imagem do perfil.', false);
        $edit->assertSee('/storage/'.$this->admin->avatar_path, false);
        $edit->assertSee('data-avatar-preview', false);
        $edit->assertDontSee('x-if="preview"', false);

        $profile = $this->actingAs($this->admin)
            ->get(route('admin.profile.edit'))
            ->assertOk();

        $profile->assertSee('/storage/'.$this->admin->avatar_path, false);
        $profile->assertSee('data-avatar-preview-image', false);
    }

    public function test_avatar_update_requires_file_or_removal(): void
    {
        $this->actingAs($this->admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.avatar'), [])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasErrors('avatar');
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $user = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_non_admin_cannot_login_to_panel(): void
    {
        $user = User::factory()->withoutAdminAccess()->create([
            'email' => 'ops@buriti.dev.br',
            'password' => 'password',
        ]);

        $this->from(route('login'))->post(route('login'), [
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors('login');

        $this->assertGuest();
        $this->assertDatabaseHas('login_activities', [
            'email' => $user->email,
            'successful' => 0,
        ]);
    }

    public function test_successful_login_records_activity(): void
    {
        $this->post(route('login'), [
            'login' => $this->admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->assertNotNull($this->admin->fresh()->last_login_at);
        $this->assertDatabaseHas('login_activities', [
            'user_id' => $this->admin->id,
            'successful' => 1,
        ]);
    }

    public function test_profile_page_loads_for_authenticated_user(): void
    {
        LoginActivity::query()->create([
            'user_id' => $this->admin->id,
            'email' => $this->admin->email,
            'successful' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'created_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee('Meu perfil', false)
            ->assertSee('Sessões ativas', false)
            ->assertSee('Histórico de login', false);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->get(route('home'));

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
