<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
    }

    public function test_integrations_page_shows_trello_notion_and_roadmap(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.integrations.edit'))
            ->assertOk()
            ->assertSee('Trello', false)
            ->assertSee('Notion', false)
            ->assertSee('Telegram', false)
            ->assertSee('Como usar as ferramentas com o site', false)
            ->assertSee('Captura e qualificação', false)
            ->assertSee('Planejamento da entrega', false);
    }

    public function test_admin_can_save_trello_and_notion_settings(): void
    {
        $this->actingAs($this->admin)->put(route('admin.integrations.update'), [
            'trello_board_id' => 'board123',
            'trello_board_url' => 'https://trello.com/b/abc/buriti',
            'trello_list_todo_id' => 'list456',
            'notion_database_id' => 'db789',
            'notion_workspace_url' => 'https://www.notion.so/workspace',
            'notion_default_page_url' => 'https://www.notion.so/playbook',
            'telegram_allowed_chat_ids' => '1001',
            'telegram_notify_chat_id' => '1001',
        ])->assertRedirect(route('admin.integrations.edit'));

        $settings = app(SettingService::class)->all();

        $this->assertSame('board123', $settings['trello_board_id']);
        $this->assertSame('https://trello.com/b/abc/buriti', $settings['trello_board_url']);
        $this->assertSame('db789', $settings['notion_database_id']);
        $this->assertSame('https://www.notion.so/playbook', $settings['notion_default_page_url']);
        $this->assertSame('1001', $settings['telegram_allowed_chat_ids']);
    }
}
