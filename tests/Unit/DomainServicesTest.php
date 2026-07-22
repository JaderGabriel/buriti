<?php

namespace Tests\Unit;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_enums_expose_labels_and_options(): void
    {
        $this->assertSame('Ativo', ProjectStatus::Active->label());
        $this->assertSame('A fazer', TaskStatus::Todo->label());
        $this->assertSame('Alta', TaskPriority::High->label());
        $this->assertArrayHasKey('active', ProjectStatus::options());
        $this->assertSame(['todo', 'doing', 'done'], TaskStatus::boardOrder());
    }

    public function test_task_builds_google_calendar_url(): void
    {
        $task = Task::factory()->make([
            'title' => 'Reunião com cliente',
            'description' => 'Alinhar escopo',
            'due_at' => now()->startOfHour(),
        ]);

        $url = $task->googleCalendarCreateUrl();

        $this->assertStringContainsString('calendar.google.com/calendar/render', $url);
        $this->assertStringContainsString('action=TEMPLATE', $url);
        $this->assertStringContainsString('text=Reuni', $url);
        $this->assertStringContainsString('details=Alinhar', $url);
        $this->assertStringContainsString('location=Google+Meet', $url);
    }

    public function test_google_calendar_service_reports_integration_levels(): void
    {
        $service = app(\App\Services\GoogleCalendarService::class);

        $status = $service->integrationStatus();

        $this->assertArrayHasKey('level', $status);
        $this->assertSame('https://meet.google.com/new', $service->instantMeetUrl());
    }

    public function test_setting_service_normalizes_google_calendar_iframe(): void
    {
        $service = app(SettingService::class);

        $service->putMany([
            'google_calendar_embed' => '<iframe src="https://calendar.google.com/calendar/embed?src=demo" width="800"></iframe>',
        ]);

        $this->assertSame(
            'https://calendar.google.com/calendar/embed?src=demo',
            $service->calendarSrc()
        );
    }

    public function test_setting_service_rejects_non_google_calendar_urls(): void
    {
        $service = app(SettingService::class);

        $service->putMany([
            'google_calendar_embed' => 'https://evil.example/embed',
        ]);

        $this->assertNull($service->calendarSrc());
    }
}
