<?php

namespace App\Services\Jirau;

use App\Enums\NotificationType;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\JirauComment;
use App\Models\User;
use App\Services\SettingService;
use App\Services\Telegram\TelegramApiClient;
use App\Services\UserNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class JirauNotifier
{
    public function __construct(
        private TelegramApiClient $telegram,
        private SettingService $settings,
        private UserNotificationService $inbox,
    ) {}

    public function demandCreated(Jirau $jirau, JirauCard $card, User $actor): void
    {
        if ($this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);

        $this->notifyCard(
            'Nova demanda no Jirau',
            $jirau,
            $card,
            [
                'De: '.$this->escape($actor->name).' (@'.$this->escape($actor->username).')',
                'Tipo: '.$this->escape($card->type->label()).' · Prioridade: '.$this->escape($card->priority->label()),
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
            $this->columnWatchers($card->column),
            actor: $actor,
        );
    }

    public function cardCreated(Jirau $jirau, JirauCard $card, User $actor): void
    {
        if ($this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);

        $this->notifyCard(
            'Cartão criado no Jirau',
            $jirau,
            $card,
            [
                'Por: '.$this->escape($actor->name),
                'Tipo: '.$this->escape($card->type->label()).' · Prioridade: '.$this->escape($card->priority->label()),
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
            $this->priorityForCard($card),
            actor: $actor,
        );
    }

    public function cardUpdated(Jirau $jirau, JirauCard $card, User $actor, array $changes): void
    {
        if ($this->silenced($jirau) || $changes === []) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);

        $this->notifyCard(
            'Cartão atualizado no Jirau',
            $jirau,
            $card,
            array_merge(
                ['Por: '.$this->escape($actor->name)],
                $changes,
            ),
            $this->priorityForCard($card),
            actor: $actor,
        );
    }

    public function cardMoved(Jirau $jirau, JirauCard $card, User $actor, JirauColumn $from, JirauColumn $to, bool $cataloged): void
    {
        if ($this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);
        $to->loadMissing('watchers');

        $title = $cataloged ? 'Demanda catalogada no Jirau' : 'Cartão movido no Jirau';

        $due = $card->due_at?->timezone(config('app.timezone'))?->format('d/m/Y H:i');

        $this->notifyCard(
            $title,
            $jirau,
            $card,
            [
                'Por: '.$this->escape($actor->name),
                $this->escape($from->name).' → '.$this->escape($to->name),
                $due ? 'Prazo: '.$this->escape($due) : null,
            ],
            $this->mergeUsers($to->watchers, $card->responsiblePeople()),
            actor: $actor,
        );
    }

    public function cardArchived(Jirau $jirau, JirauCard $card, User $actor): void
    {
        if ($this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);

        $this->notifyCard(
            'Cartão arquivado no Jirau',
            $jirau,
            $card,
            ['Por: '.$this->escape($actor->name)],
            $this->priorityForCard($card),
            actor: $actor,
        );
    }

    public function commentFromMember(Jirau $jirau, JirauCard $card, JirauComment $comment, User $actor): void
    {
        if ($actor->is_admin || $this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);

        $this->notifyCard(
            'Comentário no Jirau',
            $jirau,
            $card,
            [
                $this->escape($actor->name).': '.$this->escape(Str::limit($comment->body, 180)),
            ],
            $this->priorityForCard($card),
            cta: 'Abrir cartão',
            actor: $actor,
        );
    }

    public function dueReminder(Jirau $jirau, JirauCard $card): void
    {
        if ($this->silenced($jirau)) {
            return;
        }

        $card->loadMissing(['column.watchers', 'assignee', 'responsibles']);
        $due = $card->due_at?->timezone(config('app.timezone'))?->format('d/m/Y H:i');

        $this->notifyCard(
            'Prazo do Jirau em breve',
            $jirau,
            $card,
            [
                $due ? 'Vence '.$this->escape($due) : null,
                $card->column ? 'Coluna: '.$this->escape($card->column->name) : null,
            ],
            $this->priorityForCard($card),
            afterResponse: false,
        );
    }

    /**
     * @param  list<string|null>  $lines
     * @param  iterable<int, User>|Collection<int, User>  $priorityUsers
     */
    private function notifyCard(
        string $title,
        Jirau $jirau,
        JirauCard $card,
        array $lines,
        iterable $priorityUsers = [],
        bool $afterResponse = true,
        string $cta = 'Abrir no Jirau',
        ?User $actor = null,
    ): void {
        $audience = $this->boardAudience($jirau, $priorityUsers);

        $this->deliver($this->cardMessage($title, $jirau, $card, $lines, $cta), $priorityUsers, $afterResponse, $audience);

        $plain = collect($lines)
            ->filter(fn ($line) => is_string($line) && $line !== '')
            ->map(fn (string $line) => html_entity_decode(strip_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->filter()
            ->all();

        $this->inbox->notify(
            $audience->reject(fn (User $user) => $actor && (int) $user->id === (int) $actor->id)->values(),
            NotificationType::Jirau,
            $title,
            implode(' · ', array_filter([
                $card->key().' '.$card->title,
                ...$plain,
            ])),
            route('jirau.show', ['jirau' => $jirau, 'cartao' => $card->id]),
        );
    }

    /**
     * @param  iterable<int, User>|Collection<int, User>  $priorityUsers
     * @return Collection<int, User>
     */
    private function boardAudience(Jirau $jirau, iterable $priorityUsers = []): Collection
    {
        $jirau->loadMissing('members');
        $admins = User::query()->where('is_admin', true)->where('is_active', true)->get();

        return $this->mergeUsers($this->mergeUsers($jirau->members, $admins), $priorityUsers);
    }

    /**
     * @param  iterable<int, User>|Collection<int, User>  $priorityUsers
     * @param  Collection<int, User>|null  $audience
     */
    public function deliver(string $text, iterable $priorityUsers = [], bool $afterResponse = true, ?Collection $audience = null): void
    {
        $priorityIds = collect($priorityUsers)
            ->filter()
            ->pluck('telegram_chat_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        $teamIds = $audience === null
            ? collect($this->chatIds())
            : $this->chatIdsFor($audience);
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

    private function silenced(Jirau $jirau): bool
    {
        $jirau->loadMissing('project');

        return $jirau->isDemo();
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
        $card->loadMissing(['assignee', 'responsibles']);

        return $this->mergeUsers($this->columnWatchers($card->column), $card->responsiblePeople());
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
    private function mergeUsers(iterable $users, mixed $extra = null): Collection
    {
        $merged = collect($users);
        if ($extra instanceof User) {
            $merged->push($extra);
        } elseif (is_iterable($extra)) {
            $merged = $merged->concat($extra);
        }

        return $merged->filter()->unique('id')->values();
    }

    /** @return list<string> */
    private function chatIds(): array
    {
        return $this->chatIdsFor(
            User::assignableOnJirau()->whereNotNull('telegram_chat_id')->get()
        );
    }

    /**
     * @param  Collection<int, User>  $users
     * @return list<string>
     */
    private function chatIdsFor(Collection $users): array
    {
        $ids = $users
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
