<?php

namespace App\Services;

use App\Enums\OpportunityStage;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Telegram\TelegramApiClient;

class OpportunityFollowUpService
{
    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
    ) {}

    /**
     * @return array{checked: int, notified: int}
     */
    public function notifyStale(int $days = 7): array
    {
        $cutoff = now()->subDays(max(1, $days));

        $opportunities = Opportunity::query()
            ->with(['contact', 'clientCompany'])
            ->whereIn('stage', [
                OpportunityStage::Qualified->value,
                OpportunityStage::Proposal->value,
                OpportunityStage::Negotiation->value,
            ])
            ->whereDoesntHave('activities', fn ($q) => $q->where('happened_at', '>=', $cutoff))
            ->get();

        $notified = 0;
        foreach ($opportunities as $opportunity) {
            if ($this->notify($opportunity, $days)) {
                $notified++;
            }
        }

        return [
            'checked' => $opportunities->count(),
            'notified' => $notified,
        ];
    }

    private function notify(Opportunity $opportunity, int $days): bool
    {
        $chatIds = $this->chatIds();
        if ($chatIds === [] || ! $this->telegram->configured()) {
            return false;
        }

        $url = route('admin.opportunities.edit', $opportunity);
        $stage = $opportunity->stage?->label() ?? $opportunity->stage?->value;
        $text = implode("\n", array_filter([
            '⏳ <b>Follow-up comercial</b>',
            '',
            'Oportunidade <b>'.$this->escape($opportunity->title).'</b> em <i>'.$this->escape((string) $stage).'</i>',
            'sem actividade há '.$days.'+ dias.',
            $opportunity->contact ? '👤 '.$this->escape($opportunity->contact->name) : null,
            $opportunity->companyLabel() ? '🏢 '.$this->escape($opportunity->companyLabel()) : null,
            '',
            '<a href="'.$this->escape($url).'">Abrir no admin</a>',
        ]));

        $ok = false;
        foreach ($chatIds as $chatId) {
            if ($this->telegram->sendMessage($chatId, $text)) {
                $ok = true;
            }
        }

        return $ok;
    }

    /** @return list<string> */
    private function chatIds(): array
    {
        $ids = User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereNotNull('telegram_chat_id')
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->values();

        $notify = trim((string) ($this->settings->get('telegram_notify_chat_id') ?? ''));
        if ($notify !== '') {
            $ids->push($notify);
        }

        return $ids->unique()->values()->all();
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
