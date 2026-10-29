<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Models\Contact;
use App\Models\ContactMessage;
use App\Models\CrmActivity;
use App\Models\Opportunity;
use App\Models\OpportunityStageEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Support\Facades\Cache;

class OpportunityFollowUpService
{
    public const CACHE_PREFIX = 'crm:telegram-weekly-digest:';

    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
        private FunnelMetricsService $funnel,
    ) {}

    /**
     * @return array{sent: bool, chats: int, skipped: bool}
     */
    public function notifyWeeklySummary(int $days = 7, bool $force = false): array
    {
        $days = max(1, $days);
        $weekKey = self::CACHE_PREFIX.now()->format('o-W');

        if (! $force && Cache::has($weekKey)) {
            return ['sent' => false, 'chats' => 0, 'skipped' => true];
        }

        $chatIds = $this->chatIds();
        if ($chatIds === [] || ! $this->telegram->configured()) {
            return ['sent' => false, 'chats' => 0, 'skipped' => false];
        }

        $text = $this->summaryText($days);
        $chats = 0;
        foreach ($chatIds as $chatId) {
            if ($this->telegram->sendMessage($chatId, $text)) {
                $chats++;
            }
        }

        if ($chats > 0) {
            Cache::put($weekKey, true, now()->addDays($days + 1));
        }

        return [
            'sent' => $chats > 0,
            'chats' => $chats,
            'skipped' => false,
        ];
    }

    private function summaryText(int $days): string
    {
        $from = now()->subDays($days)->startOfDay();
        $funnel = $this->funnel->snapshot();
        $openCount = Opportunity::query()->open()->count();
        $openValue = (float) Opportunity::query()->open()->sum('value');
        $stageBits = [];
        foreach ([
            OpportunityStage::Lead,
            OpportunityStage::Qualified,
            OpportunityStage::Proposal,
            OpportunityStage::Negotiation,
        ] as $stage) {
            $count = (int) ($funnel['count_by_stage'][$stage->value] ?? 0);
            $stageBits[] = $stage->label().' '.$count;
        }

        $won = OpportunityStageEvent::query()
            ->where('to_stage', OpportunityStage::Won->value)
            ->where('changed_at', '>=', $from)
            ->count();
        $lost = OpportunityStageEvent::query()
            ->where('to_stage', OpportunityStage::Lost->value)
            ->where('changed_at', '>=', $from)
            ->count();

        return implode("\n", array_filter([
            '📊 <b>Resumo comercial da semana</b>',
            $from->format('d/m').' – '.now()->format('d/m'),
            '',
            'Novos contatos: <b>'.Contact::query()->where('created_at', '>=', $from)->count().'</b>',
            'Actividades: <b>'.CrmActivity::query()->where('happened_at', '>=', $from)->count().'</b>',
            'Oportunidades abertas: <b>'.$openCount.'</b> · '.$this->money($openValue),
            implode(' · ', $stageBits),
            'Fechadas na semana: ganhas <b>'.$won.'</b> · perdidas <b>'.$lost.'</b>',
            'Tarefas em aberto: <b>'.Task::query()->open()->count().'</b>',
            'Mensagens por ler: <b>'.ContactMessage::query()->unread()->count().'</b>',
            '',
            'CRM: <code>/oportunidades</code> · <code>/contatos</code> · <code>/tarefas</code>',
        ]));
    }

    private function money(float $value): string
    {
        return 'R$ '.number_format($value, 0, ',', '.');
    }

    /** @return list<string> */
    private function chatIds(): array
    {
        $ids = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => $this->normalizeChatId((string) $id))
            ->filter()
            ->values();

        $notify = $this->normalizeChatId((string) ($this->settings->get('telegram_notify_chat_id') ?? ''));
        if ($notify !== '') {
            $ids->push($notify);
        }

        return $ids->unique()->values()->all();
    }

    private function normalizeChatId(string $id): string
    {
        $id = trim($id);

        return preg_match('/^-?\d+$/', $id) === 1 ? $id : '';
    }
}
