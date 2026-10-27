<?php

namespace App\Services\Jirau;

use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\JirauComment;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JirauNotifier
{
    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
    ) {}

    public function demandCreated(Jirau $jirau, JirauCard $card, User $actor): void
    {
        $card->loadMissing(['column.watchers', 'assignee']);

        $this->deliver($this->cardMessage(
            'Nova demanda no Jirau',
            $jirau,
            $card,
            [
                'De: '.$this->escape($actor->name).' (@'.$this->escape($actor->username).')',
                'Tipo: '.$this->escape($card->type->label()).' · Prioridade: '.$this->escape($card->priority->label()),
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
        ), $this->columnWatchers($card->column));
    }

    public function cardCreated(Jirau $jirau, JirauCard $card, User $actor): void
    {
        $card->loadMissing(['column.watchers', 'assignee']);

        $this->deliver($this->cardMessage(
            'Cartão criado no Jirau',
            $jirau,
            $card,
            [
                'Por: '.$this->escape($actor->name),
                'Tipo: '.$this->escape($card->type->label()).' · Prioridade: '.$this->escape($card->priority->label()),
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
        ), $this->priorityForCard($card));
    }

    public function cardUpdated(Jirau $jirau, JirauCard $card, User $actor, array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee']);

        $this->deliver($this->cardMessage(
            'Cartão atualizado no Jirau',
            $jirau,
            $card,
            array_merge(
                ['Por: '.$this->escape($actor->name)],
                $changes,
            ),
        ), $this->priorityForCard($card));
    }

    public function cardMoved(Jirau $jirau, JirauCard $card, User $actor, JirauColumn $from, JirauColumn $to, bool $cataloged): void
    {
        $card->loadMissing(['column.watchers', 'assignee']);
        $to->loadMissing('watchers');

        $title = $cataloged ? 'Demanda catalogada no Jirau' : 'Cartão movido no Jirau';

        $due = $card->due_at?->timezone(config('app.timezone'))?->format('d/m/Y H:i');

        $this->deliver($this->cardMessage(
            $title,
            $jirau,
            $card,
            [
                'Por: '.$this->escape($actor->name),
                $this->escape($from->name).' → '.$this->escape($to->name),
                $due ? 'Prazo: '.$this->escape($due) : null,
            ],
        ), $this->mergeUsers($to->watchers, $card->assignee));
    }

    public function cardArchived(Jirau $jirau, JirauCard $card, User $actor): void
    {
        $card->loadMissing(['column.watchers', 'assignee']);

        $this->deliver($this->cardMessage(
            'Cartão arquivado no Jirau',
            $jirau,
            $card,
            ['Por: '.$this->escape($actor->name)],
        ), $this->priorityForCard($card));
    }

    public function commentFromMember(Jirau $jirau, JirauCard $card, JirauComment $comment, User $actor): void
    {
        if ($actor->is_admin) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee']);

        $this->deliver($this->cardMessage(
            'Comentário no Jirau',
            $jirau,
            $card,
            [
                $this->escape($actor->name).': '.$this->escape(Str::limit($comment->body, 180)),
            ],
            'Abrir cartão',
        ), $this->priorityForCard($card));
    }

    public function dueReminder(Jirau $jirau, JirauCard $card): void
    {
        $card->loadMissing(['column.watchers', 'assignee']);
        $due = $card->due_at?->timezone(config('app.timezone'))?->format('d/m/Y H:i');

        $this->deliver($this->cardMessage(
            'Prazo do Jirau em breve',
            $jirau,
            $card,
            [
                $due ? 'Vence '.$this->escape($due) : null,
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
        ), $this->priorityForCard($card), afterResponse: false);
    }

    /**
     * @param  iterable<int, User>|Collection<int, User>  $priorityUsers
     */
    public function deliver(string $text, iterable $priorityUsers = [], bool $afterResponse = true): void
    {
        $priorityIds = collect($priorityUsers)
            ->filter()
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $teamIds = collect($this->chatIds());
        $priorityBody = '<b>⚡ Prioritário</b>'."\n".$text;

        $send = function () use ($text, $priorityBody, $priorityIds, $teamIds) {
            $notifier = app(self::class);
            foreach ($priorityIds as $chatId) {
                $notifier->telegram->sendMessage($chatId, $priorityBody);
            }
            foreach ($teamIds as $chatId) {
                if ($priorityIds->contains($chatId)) {
                    continue;
                }
                $notifier->telegram->sendMessage($chatId, $text);
            }
        };

        if ($afterResponse) {
            dispatch($send)->afterResponse();

            return;
        }

        $send();
    }

    public function broadcast(string $text): void
    {
        foreach ($this->chatIds() as $chatId) {
            $this->telegram->sendMessage($chatId, $text);
        }
    }

    /** @param  list<string|null>  $lines */
    private function cardMessage(string $title, Jirau $jirau, JirauCard $card, array $lines, string $cta = 'Abrir no Jirau'): string
    {
        $project = $jirau->project?->name ?? $jirau->name;
        $url = route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]);

        return implode("\n", array_values(array_filter([
            '<b>'.$this->escape($title).'</b>',
            $this->escape($project),
            '',
            '<b>'.$this->escape($card->key()).'</b> '.$this->escape($card->title),
            ...$lines,
            '',
            '<a href="'.$this->escape($url).'">'.$this->escape($cta).'</a>',
        ], fn ($line) => $line !== null && $line !== '')));
    }

    /** @return Collection<int, User> */
    private function priorityForCard(JirauCard $card): Collection
    {
        return $this->mergeUsers($this->columnWatchers($card->column), $card->assignee);
    }

    /** @return Collection<int, User> */
    private function columnWatchers(?JirauColumn $column): Collection
    {
        if (! $column) {
            return collect();
        }

        $column->loadMissing('watchers');

        return $column->watchers;
    }

    /** @return Collection<int, User> */
    private function mergeUsers(iterable $users, ?User $extra): Collection
    {
        $merged = collect($users);
        if ($extra) {
            $merged->push($extra);
        }

        return $merged->filter()->unique('id')->values();
    }

    /** @return list<string> */
    private function chatIds(): array
    {
        $ids = User::assignableOnJirau()
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
