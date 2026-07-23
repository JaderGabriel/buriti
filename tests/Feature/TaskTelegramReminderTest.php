<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskTelegramReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskTelegramReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.telegram.bot_token' => 'test-token',
        ]);
    }

    public function test_sends_telegram_reminder_when_task_is_due_within_10_minutes(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $user = User::factory()->create();
        $user->forceFill([
            'is_admin' => true,
            'is_active' => true,
            'telegram_chat_id' => '424242',
        ])->save();

        $task = Task::factory()->create([
            'title' => 'Reunião com cliente',
            'status' => TaskStatus::Todo,
            'due_at' => now()->addMinutes(8),
            'project_id' => null,
        ]);
        $task->forceFill(['user_id' => $user->id])->save();

        $result = app(TaskTelegramReminderService::class)->sendDueReminders();

        $this->assertSame(1, $result['sent']);
        $this->assertNotNull($task->fresh()->telegram_reminder_sent_at);

        Http::assertSent(function ($request) {
            $data = $request->data();
            $text = (string) ($data['text'] ?? '');

            return str_contains($request->url(), 'sendMessage')
                && ($data['chat_id'] ?? null) == '424242'
                && str_contains($text, 'Lembrete de agenda')
                && str_contains($text, 'Reunião com cliente')
                && str_contains($text, 'Abrir na agenda do CRM');
        });
    }

    public function test_does_not_send_duplicate_reminder(): void
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);

        $user = User::factory()->create();
        $user->forceFill(['telegram_chat_id' => '424242'])->save();

        $task = Task::factory()->create([
            'status' => TaskStatus::Doing,
            'due_at' => now()->addMinutes(5),
            'project_id' => null,
        ]);
        $task->forceFill([
            'user_id' => $user->id,
            'telegram_reminder_sent_at' => now()->subMinute(),
        ])->save();

        $result = app(TaskTelegramReminderService::class)->sendDueReminders();

        $this->assertSame(0, $result['sent']);
        Http::assertNothingSent();
    }

    public function test_skips_tasks_outside_window_or_without_telegram(): void
    {
        Http::fake();

        $withTelegram = User::factory()->create();
        $withTelegram->forceFill(['telegram_chat_id' => '111'])->save();

        $withoutTelegram = User::factory()->create();
        $withoutTelegram->forceFill(['telegram_chat_id' => null])->save();

        $soon = Task::factory()->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->addMinutes(8),
            'project_id' => null,
        ]);
        $soon->forceFill(['user_id' => $withoutTelegram->id])->save();

        $later = Task::factory()->create([
            'status' => TaskStatus::Todo,
            'due_at' => now()->addMinutes(40),
            'project_id' => null,
        ]);
        $later->forceFill(['user_id' => $withTelegram->id])->save();

        $done = Task::factory()->done()->create([
            'due_at' => now()->addMinutes(5),
            'project_id' => null,
        ]);
        $done->forceFill(['user_id' => $withTelegram->id])->save();

        $result = app(TaskTelegramReminderService::class)->sendDueReminders();

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['skipped']);
        Http::assertNothingSent();
    }

    public function test_creating_task_via_admin_stores_creator(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $this->actingAs($admin)->post(route('admin.tasks.store'), [
            'title' => 'Nova tarefa',
            'status' => 'todo',
            'priority' => 'medium',
            'due_at' => now()->addHour()->format('Y-m-d\TH:i'),
            'want_meet' => '0',
        ])->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'title' => 'Nova tarefa',
            'user_id' => $admin->id,
        ]);
    }

    public function test_updating_due_at_resets_reminder_flag(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_active' => true,
        ]);

        $task = Task::factory()->create([
            'title' => 'Tarefa',
            'status' => TaskStatus::Todo,
            'priority' => 'medium',
            'due_at' => now()->addDay(),
            'project_id' => null,
            'want_meet' => false,
        ]);
        $task->forceFill([
            'user_id' => $admin->id,
            'telegram_reminder_sent_at' => now(),
        ])->save();

        $this->actingAs($admin)->put(route('admin.tasks.update', $task), [
            'title' => 'Tarefa',
            'status' => 'todo',
            'priority' => 'medium',
            'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'want_meet' => '0',
        ])->assertRedirect();

        $this->assertNull($task->fresh()->telegram_reminder_sent_at);
    }
}
