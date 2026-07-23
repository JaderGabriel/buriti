<?php

namespace Tests\Feature\Admin;

use App\Models\Task;
use App\Models\User;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SettingService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->settings = app(SettingService::class);

        config([
            'services.google.client_id' => 'test-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.refresh_token' => null,
            'services.google.redirect_uri' => 'https://buriti.test/admin/google/callback',
        ]);
    }

    public function test_sync_stays_in_crm_when_api_not_ready(): void
    {
        $task = Task::factory()->create([
            'want_meet' => true,
            'meet_url' => null,
            'google_event_id' => null,
        ]);

        $this->actingAs($this->admin)
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.google', $task))
            ->assertRedirect(route('admin.tasks.index'))
            ->assertSessionHas('error');

        $this->assertNull($task->fresh()->google_event_id);
    }

    public function test_create_task_with_api_generates_meet_and_stays_in_crm(): void
    {
        $this->settings->putMany([
            'google_auto_sync' => '1',
            'google_calendar_id' => 'primary',
        ]);
        $this->settings->putSecret('google_refresh_token', 'refresh-token-test');

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-test',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'id' => 'google-event-123',
                'hangoutLink' => 'https://meet.google.com/abc-defg-hij',
                'conferenceData' => [
                    'entryPoints' => [
                        ['entryPointType' => 'video', 'uri' => 'https://meet.google.com/abc-defg-hij'],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.tasks.store'), [
                'title' => 'Reunião API Meet',
                'description' => 'Teste sync',
                'status' => 'todo',
                'priority' => 'medium',
                'due_at' => now()->addDay()->format('Y-m-d\\TH:i'),
                'want_meet' => '1',
                'return_view' => 'calendar',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $task = Task::query()->where('title', 'Reunião API Meet')->first();

        $this->assertNotNull($task);
        $this->assertSame('google-event-123', $task->google_event_id);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $task->meet_url);
    }

    public function test_oauth_connect_redirects_to_google(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.google.connect'));

        $response->assertRedirect();
        $target = $response->headers->get('Location');

        $this->assertNotNull($target);
        $this->assertStringContainsString('accounts.google.com/o/oauth2/v2/auth', $target);
        $this->assertStringContainsString('client_id=test-client-id', $target);
        $this->assertTrue(session()->has('google_oauth_state'));
    }
}
