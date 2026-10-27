<?php

namespace Tests\Feature\Account;

use App\Enums\JirauCardPriority;
use App\Enums\NotificationType;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_topbar_shows_notifications_next_to_profile(): void
    {
        $admin = User::factory()->create();

        $html = $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notificações"', false)
            ->assertSee('admin-topbar__account', false)
            ->assertSee('admin-notify', false)
            ->assertSee('data-admin-notify-panel', false)
            ->assertSee('aria-label="Fechar notificações"', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/data-admin-notify-panel[^>]*\bhidden\b|\bhidden\b[^>]*data-admin-notify-panel/',
            $html
        );

        $account = strpos($html, 'admin-topbar__account');
        $bell = strpos($html, 'admin-notify');
        $theme = strpos($html, 'data-theme-toggle');
        $site = strpos($html, 'admin-icon-btn--site');
        $logout = strpos($html, 'admin-icon-btn--logout');
        $chip = strpos($html, 'admin-user--chip');

        $this->assertNotFalse($account);
        $this->assertNotFalse($bell);
        $this->assertNotFalse($theme);
        $this->assertNotFalse($site);
        $this->assertNotFalse($logout);
        $this->assertNotFalse($chip);
        $this->assertLessThan($bell, $account);
        $this->assertLessThan($theme, $bell);
        $this->assertLessThan($site, $theme);
        $this->assertLessThan($logout, $site);
        $this->assertLessThan($chip, $logout);
        $this->assertSame(1, substr_count($html, 'admin-user--chip'));
        $this->assertStringContainsString('@'.$admin->username, $html);
    }

    public function test_jirau_staff_sees_notifications_in_topbar_and_inbox(): void
    {
        $staff = User::factory()->staff()->create();
        UserNotification::factory()->create([
            'user_id' => $staff->id,
            'title' => 'Cartão movido no Jirau',
        ]);

        $this->actingAs($staff)
            ->get(route('jirau.home'))
            ->assertOk()
            ->assertSee('data-admin-notify-toggle', false)
            ->assertSee('aria-label="Fechar notificações"', false)
            ->assertSee('admin-chrome', false)
            ->assertSee('Novo Teams', false)
            ->assertSee('Novo Jitsi', false)
            ->assertSee('Cartão movido no Jirau');

        $this->actingAs($staff)
            ->get(route('account.notifications.index'))
            ->assertOk()
            ->assertSee('Notificações')
            ->assertSee('Cartão movido no Jirau');
    }

    public function test_site_message_notifies_admins_and_appears_in_inbox(): void
    {
        $admin = User::factory()->create();
        $staff = User::factory()->staff()->create();

        $this->from(route('home'))->post(route('contact.store'), [
            'name' => 'Cliente Teste',
            'email' => 'cliente@empresa.com',
            'phone_country' => 'BR',
            'phone_number' => '38991758416',
            'preferred_channel' => 'whatsapp',
            'company' => 'Empresa X',
            'subject' => 'Proposta de TI',
            'message' => 'Preciso de consultoria e desenvolvimento.',
            'privacy_consent' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $admin->id,
            'type' => NotificationType::Message->value,
            'title' => 'Nova mensagem do site',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $staff->id,
            'title' => 'Nova mensagem do site',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Nova mensagem do site')
            ->assertSee('aria-label="Notificações (1 não lidas)"', false);

        $notification = UserNotification::query()->where('user_id', $admin->id)->first();
        $this->assertNotNull($notification);

        $this->actingAs($admin)
            ->get(route('account.notifications.open', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_open_someone_elses_notification(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $notification = UserNotification::factory()->create([
            'user_id' => $other->id,
            'url' => route('admin.dashboard'),
        ]);

        $this->actingAs($admin)
            ->get(route('account.notifications.open', $notification))
            ->assertNotFound();
    }

    public function test_mark_all_read_clears_unread_badge(): void
    {
        $admin = User::factory()->create();
        UserNotification::factory()->count(2)->create(['user_id' => $admin->id]);

        $this->actingAs($admin)
            ->from(route('account.notifications.index'))
            ->post(route('account.notifications.read-all'))
            ->assertRedirect(route('account.notifications.index'));

        $this->assertSame(0, $admin->inboxNotifications()->unread()->count());

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('aria-label="Notificações"', false)
            ->assertDontSee('não lidas', false);
    }

    public function test_guest_is_redirected_from_inbox(): void
    {
        $this->get(route('account.notifications.index'))
            ->assertRedirect(route('login'));
    }

    public function test_unsafe_notification_url_stays_in_inbox(): void
    {
        $admin = User::factory()->create();
        $notification = UserNotification::factory()->create([
            'user_id' => $admin->id,
            'url' => 'https://evil.example/phish',
        ]);

        $this->actingAs($admin)
            ->get(route('account.notifications.open', $notification))
            ->assertRedirect(route('account.notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cataloged_move_notifies_team_except_actor(): void
    {
        $this->fakeTelegramApi();

        $admin = User::factory()->telegramLinked('555001')->create();
        $staff = User::factory()->staff()->telegramLinked('888002')->create(['name' => 'Rafa Dev']);
        $jirau = Jirau::factory()->create(['created_by' => $admin->id]);
        $inbox = $jirau->columns()->where('slug', 'inbox')->first();
        $backlog = $jirau->columns()->where('slug', 'backlog')->first();

        $card = JirauCard::factory()->demand()->create([
            'jirau_id' => $jirau->id,
            'column_id' => $inbox->id,
            'title' => 'Ajuste no boleto',
        ]);

        $this->actingAs($admin)
            ->postJson(route('jirau.cards.move', [$jirau, $card]), [
                'column_id' => $backlog->id,
                'ordered_ids' => [$card->id],
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $staff->id,
            'title' => 'Demanda catalogada no Jirau',
        ]);
        $this->assertDatabaseMissing('user_notifications', [
            'user_id' => $admin->id,
            'title' => 'Demanda catalogada no Jirau',
        ]);
    }

    public function test_demo_board_does_not_create_in_app_notifications(): void
    {
        $admin = User::factory()->create();
        $demo = Jirau::factory()->create([
            'name' => Jirau::DEMO_NAME,
            'created_by' => $admin->id,
        ]);
        $member = User::factory()->withoutAdminAccess()->create();
        $demo->members()->attach($member->id);

        $this->actingAs($member)
            ->post(route('jirau.demands.store', $demo), [
                'title' => 'Pedido do Demo',
                'priority' => JirauCardPriority::High->value,
            ])
            ->assertRedirect();

        $this->assertSame(0, UserNotification::query()->count());
    }
}
