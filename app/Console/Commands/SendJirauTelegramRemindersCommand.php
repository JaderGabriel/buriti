<?php

namespace App\Console\Commands;

use App\Models\JirauCard;
use App\Services\Jirau\JirauNotifier;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendJirauTelegramRemindersCommand extends Command
{
    protected $signature = 'jirau:telegram-reminders';

    protected $description = 'Envia lembretes Telegram para cartões do Jirau que vencem em até 10 minutos';

    public const LEAD_MINUTES = 10;

    public const GRACE_MINUTES = 2;

    public function handle(TelegramApiClient $api, JirauNotifier $notifier): int
    {
        if (! $api->configured()) {
            $this->info('Lembretes Jirau ignorados: bot sem token.');

            return self::SUCCESS;
        }

        $now = now(config('app.timezone'));
        $windowStart = $now->copy()->subMinutes(self::GRACE_MINUTES);
        $windowEnd = $now->copy()->addMinutes(self::LEAD_MINUTES);

        $cards = JirauCard::query()
            ->active()
            ->whereNotNull('due_at')
            ->whereNull('telegram_reminder_sent_at')
            ->where('due_at', '>=', $windowStart)
            ->where('due_at', '<=', $windowEnd)
            ->whereHas('column', fn ($query) => $query->where('is_done', false))
            ->with(['jirau.project', 'column.watchers', 'assignee'])
            ->orderBy('due_at')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($cards as $card) {
            $jirau = $card->jirau;
            if (! $jirau) {
                $skipped++;

                continue;
            }

            $notifier->dueReminder($jirau, $card);
            $card->forceFill(['telegram_reminder_sent_at' => $now->copy()])->save();
            $sent++;
        }

        Log::info('Jirau Telegram reminders', [
            'checked' => $cards->count(),
            'sent' => $sent,
            'skipped' => $skipped,
        ]);

        $this->info(sprintf('Lembretes Jirau: %d candidatas, %d enviadas, %d ignoradas.', $cards->count(), $sent, $skipped));

        return self::SUCCESS;
    }
}
