<?php

namespace App\Console\Commands;

use App\Services\TaskTelegramReminderService;
use Illuminate\Console\Command;

class SendTaskTelegramRemindersCommand extends Command
{
    protected $signature = 'tasks:telegram-reminders';

    protected $description = 'Envia lembretes Telegram para tarefas que vencem em até 10 minutos';

    public function handle(TaskTelegramReminderService $reminders): int
    {
        $result = $reminders->sendDueReminders();

        $this->info(sprintf(
            'Lembretes: %d candidatas, %d enviadas, %d ignoradas.',
            $result['checked'],
            $result['sent'],
            $result['skipped']
        ));

        return self::SUCCESS;
    }
}
