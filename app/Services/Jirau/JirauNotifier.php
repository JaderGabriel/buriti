<?php

namespace App\Services\Jirau;

use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauComment;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Support\Str;

class JirauNotifier
{
    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
    ) {}

    public function demandCreated(Jirau $jirau, JirauCard $card, User $actor): void
    {
        $project = $jirau->project?->name ?? $jirau->name;
        $url = route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);

        $card->loadMissing('column');

        $text = implode("\n", array_values(array_filter([
            '<b>Nova demanda no Jirau</b>',
            $this->escape($project),
            '',
            '<b>'.$this->escape($card->key()).'</b> '.$this->escape($card->title),
            'De: '.$this->escape($actor->name).' (@'.$this->escape($actor->username).')',
            'Tipo: '.$this->escape($card->type->label()).' · Prioridade: '.$this->escape($card->priority->label()),
            $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            '',
            '<a href="'.$this->escape($url).'">Abrir no Jirau</a>',
        ], fn ($line) => $line !== null)));

        $this->queueBroadcast($text);
    }

    public function commentFromMember(Jirau $jirau, JirauCard $card, JirauComment $comment, User $actor): void
    {
        if ($actor->is_admin) {
            return;
        }

        $project = $jirau->project?->name ?? $jirau->name;
        $url = route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);

        $text = implode("\n", [
            '<b>Comentário no Jirau</b>',
            $this->escape($project),
            '',
            '<b>'.$this->escape($card->key()).'</b> '.$this->escape($card->title),
            $this->escape($actor->name).': '.$this->escape(Str::limit($comment->body, 180)),
            '',
            '<a href="'.$this->escape($url).'">Abrir cartão</a>',
        ]);

        $this->queueBroadcast($text);
    }

    private function queueBroadcast(string $text): void
    {
        dispatch(function () use ($text) {
            app(self::class)->broadcast($text);
        })->afterResponse();
    }

    public function broadcast(string $text): void
    {
        foreach ($this->chatIds() as $chatId) {
            $this->telegram->sendMessage($chatId, $text);
        }
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
