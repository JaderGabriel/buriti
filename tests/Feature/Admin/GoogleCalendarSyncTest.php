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
        $calendarId = '7dcd190acf359a6c492c3bd92832eabfba38e19c6860ad290aa4e6d62539ffa0@group.calendar.google.com';

        $this->settings->putMany([
            'google_auto_sync' => '1',
            'google_calendar_id' => $calendarId,
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
                'colorId' => '11',
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
                'google_color_id' => '11',
                'return_view' => 'calendar',
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionMissing('error');

        $task = Task::query()->where('title', 'Reunião API Meet')->first();

        $this->assertNotNull($task);
        $this->assertSame('google-event-123', $task->google_event_id);
        $this->assertSame('https://meet.google.com/abc-defg-hij', $task->meet_url);
        $this->assertSame($calendarId, $task->google_calendar_id);
        $this->assertSame('11', $task->googleColor()?->value);

        Http::assertSent(function ($request) use ($calendarId) {
            if (! str_contains($request->url(), '/calendars/'.rawurlencode($calendarId).'/events')) {
                return false;
            }

            $body = $request->data();

            return ($body['colorId'] ?? null) === '11'
                && ($body['summary'] ?? null) === 'Reunião API Meet';
        });
    }

    public function test_resolved_calendar_id_prefers_settings_and_embed(): void
    {
        $groupId = 'abc123@group.calendar.google.com';

        $this->settings->putMany([
            'google_calendar_id' => 'primary',
            'google_calendar_embed' => 'https://calendar.google.com/calendar/embed?src='.rawurlencode($groupId).'&ctz=America%2FSao_Paulo',
        ]);

        $service = app(\App\Services\GoogleCalendarService::class);

        $this->assertSame($groupId, $service->resolvedCalendarId());
        $this->assertTrue($service->integrationStatus()['calendar_matches_embed']);
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

    public function test_agenda_shows_google_events_from_api(): void
    {
        $this->settings->putMany([
            'google_calendar_id' => 'primary',
        ]);
        $this->settings->putSecret('google_refresh_token', 'refresh-token-test');

        $start = now()->startOfMonth()->addDays(2)->setTime(10, 0);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-test',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'www.googleapis.com/calendar/v3/calendars/*' => Http::response([
                'items' => [
                    [
                        'id' => 'gcal-external-1',
                        'summary' => 'Reunião só no Google',
                        'htmlLink' => 'https://calendar.google.com/event?eid=1',
                        'colorId' => '9',
                        'start' => ['dateTime' => $start->toIso8601String()],
                        'end' => ['dateTime' => $start->copy()->addHour()->toIso8601String()],
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.tasks.index', [
                'view' => 'agenda',
                'month' => $start->format('Y-m'),
            ]))
            ->assertOk()
            ->assertSee('Reunião só no Google', false)
            ->assertSee('Google Agenda', false);
    }
}
