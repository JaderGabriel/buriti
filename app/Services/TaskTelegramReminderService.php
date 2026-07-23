<?php

namespace App\Services;

use App\Models\Task;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TaskTelegramReminderService
{
    /** Minutos antes do vencimento para disparar o lembrete. */
    public const LEAD_MINUTES = 10;

    /** Folga para atraso do cron (minutos). */
    public const GRACE_MINUTES = 2;

    public function __construct(private TelegramApiClient $api) {}

    /**
     * Envia lembretes Telegram para tarefas abertas próximas do vencimento.
     *
     * @return array{checked: int, sent: int, skipped: int}
     */
    public function sendDueReminders(): array
    {
        if (! $this->api->configured()) {
            Log::info('Task Telegram reminders skipped: bot token not configured');

            return ['checked' => 0, 'sent' => 0, 'skipped' => 0];
        }

        $now = now(config('app.timezone'));
        $windowStart = $now->copy()->subMinutes(self::GRACE_MINUTES);
        $windowEnd = $now->copy()->addMinutes(self::LEAD_MINUTES);

        $tasks = Task::query()
            ->open()
            ->whereNotNull('due_at')
            ->whereNotNull('user_id')
            ->whereNull('telegram_reminder_sent_at')
            ->where('due_at', '>=', $windowStart->format('Y-m-d H:i:s'))
            ->where('due_at', '<=', $windowEnd->format('Y-m-d H:i:s'))
            ->with(['user', 'project'])
            ->orderBy('due_at')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $chatId = trim((string) ($task->user?->telegram_chat_id ?? ''));
            if ($chatId === '') {
                Log::info('Task Telegram reminder skipped: user without chat_id', [
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                ]);
                $skipped++;

                continue;
            }

            $ok = $this->api->sendMessage($chatId, $this->messageFor($task, $now));
            if (! $ok) {
                Log::warning('Task Telegram reminder failed', [
                    'task_id' => $task->id,
                    'user_id' => $task->user_id,
                    'chat_id' => $chatId,
                ]);
                $skipped++;

                continue;
            }

            $task->forceFill([
                'telegram_reminder_sent_at' => $now->copy(),
            ])->save();
            $sent++;
        }

        return [
            'checked' => $tasks->count(),
            'sent' => $sent,
            'skipped' => $skipped,
        ];
    }

    private function messageFor(Task $task, $now): string
    {
        $timezone = config('app.timezone', 'America/Sao_Paulo');
        $due = $task->due_at?->timezone($timezone);
        $minutes = $due ? max(0, (int) round($now->diffInMinutes($due, false))) : 0;
        $url = route('admin.tasks.index', ['view' => 'agenda']);
        $project = $task->project?->name;

        $whenLabel = $minutes <= 0
            ? 'agora ('.$due?->format('d/m/Y H:i').')'
            : '~'.$minutes.' min ('.$due?->format('d/m/Y H:i').')';

        $lines = [
            '⏰ <b>Lembrete de tarefa</b>',
            '',
            '<b>'.$this->escape($task->title).'</b>',
        ];

        if ($project) {
            $lines[] = '<b>Projeto:</b> '.$this->escape($project);
        }

        $lines[] = '<b>Vence em:</b> '.$this->escape($whenLabel);

        $description = trim((string) $task->description);
        if ($description !== '') {
            $lines[] = '';
            $lines[] = $this->escape(Str::limit($description, 280));
        }

        $lines[] = '';
        $lines[] = '🔗 <a href="'.$this->escape($url).'">Abrir tarefas</a>';

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
