<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_accessible_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('E-mail ou username', false);
    }

    public function test_admin_can_authenticate_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@buriti.dev.br',
            'username' => 'adminuser',
            'password' => 'secret123',
        ]);

        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_authenticate_with_username(): void
    {
        $user = User::factory()->create([
            'email' => 'admin2@buriti.dev.br',
            'username' => 'jadergabriel',
            'password' => 'secret123',
        ]);

        $response = $this->post(route('login'), [
            'login' => 'jadergabriel',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::factory()->create(['email' => 'admin@buriti.dev.br', 'username' => 'adminonly']);

        $response = $this->from(route('login'))->post(route('login'), [
            'login' => 'admin@buriti.dev.br',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_admin(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_is_redirected_from_login_to_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_authenticated_user_can_open_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_non_admin_session_is_rejected_from_admin(): void
    {
        $user = User::factory()->withoutAdminAccess()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_inactive_admin_session_is_rejected_from_admin(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_home_admin_links_point_to_protected_dashboard(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee(route('admin.dashboard'), false);
        $response->assertDontSee('href="'.route('login').'"', false);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
