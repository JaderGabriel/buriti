<?php

namespace App\Services\Jirau;

use App\Enums\JirauCardParticipantRole;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\JirauTimeEntry;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JirauTimeService
{
    public function __construct(private AuditLogger $audit) {}

    public function runningFor(User $user): ?JirauTimeEntry
    {
        return JirauTimeEntry::query()
            ->running()
            ->where('user_id', $user->id)
            ->with(['card:id,title,jirau_id', 'column:id,name'])
            ->first();
    }

    /**
     * @return array{started: bool, entry: JirauTimeEntry, message: string}
     */
    public function toggle(User $actor, JirauCard $card): array
    {
        $card->loadMissing(['column', 'jirau']);

        $mine = JirauTimeEntry::query()
            ->running()
            ->where('card_id', $card->id)
            ->where('user_id', $actor->id)
            ->first();

        if ($mine) {
            $this->close($mine);

            return [
                'started' => false,
                'entry' => $mine->fresh(),
                'message' => 'Encerrado '.$card->key().' em '.$mine->endedLabel().'.',
            ];
        }

        $this->pauseRunningFor($actor);
        $entry = $this->open($actor, $card, $card->column);

        return [
            'started' => true,
            'entry' => $entry,
            'message' => 'Fazendo '.$card->key().' desde '.$entry->startedLabel().'.',
        ];
    }

    public function followColumn(JirauCard $card, JirauColumn $to): void
    {
        $running = JirauTimeEntry::query()
            ->running()
            ->where('card_id', $card->id)
            ->get();

        foreach ($running as $entry) {
            $user = $entry->user()->first();
            $this->close($entry);
            if ($user) {
                $this->open($user, $card, $to);
            }
        }
    }

    /**
     * Continua sessões em curso na nova etapa e dispara Fazer para quem moveu o cartão.
     *
     * @return array{
     *     card_id: int,
     *     running: bool,
     *     started_label: ?string,
     *     paused_card_id: ?int,
     *     paused_ended_label: ?string
     * }
     */
    public function onCardMoved(JirauCard $card, JirauColumn $to, User $actor): array
    {
        $card->loadMissing(['jirau']);

        $previous = JirauTimeEntry::query()
            ->running()
            ->where('user_id', $actor->id)
            ->first();

        $this->followColumn($card, $to);

        $mine = JirauTimeEntry::query()
            ->running()
            ->where('card_id', $card->id)
            ->where('user_id', $actor->id)
            ->first();

        $pausedCardId = null;
        $pausedEndedLabel = null;

        if (! $mine && $actor->isAssignableOnJirau() && $card->jirau?->is_active) {
            $this->pauseRunningFor($actor);
            if ($previous && (int) $previous->card_id !== (int) $card->id) {
                $closed = $previous->fresh();
                $pausedCardId = (int) $previous->card_id;
                $pausedEndedLabel = $closed?->endedLabel();
            }
            $mine = $this->open($actor, $card, $to);
        }

        return [
            'card_id' => (int) $card->id,
            'running' => $mine !== null,
            'started_label' => $mine?->startedLabel(),
            'paused_card_id' => $pausedCardId,
            'paused_ended_label' => $pausedEndedLabel,
        ];
    }

    public function pauseOnCard(JirauCard $card): void
    {
        JirauTimeEntry::query()
            ->running()
            ->where('card_id', $card->id)
            ->get()
            ->each(fn (JirauTimeEntry $entry) => $this->close($entry));
    }

    public function pauseOnBoard(Jirau $jirau): void
    {
        JirauTimeEntry::query()
            ->running()
            ->where('jirau_id', $jirau->id)
            ->get()
            ->each(fn (JirauTimeEntry $entry) => $this->close($entry));
    }

    /**
     * @return array{
     *     total: int,
     *     total_label: string,
     *     columns: list<array{
     *         name: string,
     *         color: string,
     *         seconds: int,
     *         label: string,
     *         rows: list<array{name: string, started: string, ended: string, running: bool, seconds: int, label: string}>
     *     }>
     * }
     */
    public function snapshot(JirauCard $card): array
    {
        $entries = $card->relationLoaded('timeEntries')
            ? $card->timeEntries
            : $card->timeEntries()->get();

        $entries->loadMissing(['user:id,name,jirau_role,is_admin', 'column:id,name,color,sort_order']);

        $total = (int) $entries->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());

        $columns = $entries
            ->sortBy([
                ['started_at', 'asc'],
                ['id', 'asc'],
            ])
            ->groupBy(fn (JirauTimeEntry $entry) => (string) ($entry->column_id ?: 'x-'.$entry->column_name))
            ->map(function (Collection $group) {
                $seconds = (int) $group->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
                $first = $group->first();
                $column = $first?->column;

                return [
                    'name' => $first?->column_name ?: 'Etapa',
                    'color' => $column?->color ?: '#5b6b82',
                    'sort' => $column?->sort_order ?? 9999,
                    'seconds' => $seconds,
                    'label' => JirauTimeEntry::formatSeconds($seconds),
                    'rows' => $group->values()->map(fn (JirauTimeEntry $entry) => [
                        'name' => $entry->user?->name ?? 'Alguém',
                        'started' => $entry->startedLabel(),
                        'ended' => $entry->isRunning() ? 'Em curso' : $entry->endedLabel(),
                        'running' => $entry->isRunning(),
                        'seconds' => $entry->elapsedSeconds(),
                        'label' => $entry->elapsedLabel(),
                    ])->all(),
                ];
            })
            ->sortBy([
                ['sort', 'asc'],
                ['name', 'asc'],
            ])
            ->map(function (array $column) {
                unset($column['sort']);

                return $column;
            })
            ->values()
            ->all();

        return [
            'total' => $total,
            'total_label' => JirauTimeEntry::formatSeconds($total),
            'columns' => $columns,
        ];
    }

    public function open(User $actor, JirauCard $card, ?JirauColumn $column = null): JirauTimeEntry
    {
        $column ??= $card->column;
        if (! $column) {
            throw ValidationException::withMessages([
                'time' => 'Este cartão não tem etapa para apontar horas.',
            ]);
        }

        return DB::transaction(function () use ($actor, $card, $column) {
            $entry = JirauTimeEntry::query()->create([
                'jirau_id' => $card->jirau_id,
                'card_id' => $card->id,
                'user_id' => $actor->id,
                'column_id' => $column->id,
                'column_name' => $column->name,
                'started_at' => now(),
                'ended_at' => null,
                'seconds' => 0,
            ]);

            $this->audit->record('jirau.time.started', $card, [
                'summary' => $card->title,
                'entry_id' => $entry->id,
                'column' => $column->name,
            ], userId: $actor->id);

            $this->ensureActor($card, $actor);

            return $entry;
        });
    }

    public function close(JirauTimeEntry $entry): JirauTimeEntry
    {
        if (! $entry->isRunning()) {
            return $entry;
        }

        $ended = now();
        $entry->forceFill([
            'ended_at' => $ended,
            'seconds' => $entry->elapsedSeconds($ended),
        ])->save();

        $this->audit->record('jirau.time.paused', $entry->card, [
            'summary' => $entry->card?->title ?? $entry->card_id,
            'entry_id' => $entry->id,
            'seconds' => $entry->seconds,
            'column' => $entry->column_name,
        ], userId: $entry->user_id);

        return $entry;
    }

    public function ensureActor(JirauCard $card, User $actor): void
    {
        if (! $actor->isAssignableOnJirau()) {
            return;
        }
        if ($card->demanderId() !== null && (int) $card->demanderId() === (int) $actor->id) {
            return;
        }
        if ($card->actors()->whereKey($actor->id)->exists()) {
            return;
        }

        $card->actors()->attach($actor->id, [
            'role' => JirauCardParticipantRole::Actor->value,
        ]);
        $card->unsetRelation('actors');
    }

    public function pauseRunningFor(User $actor): void
    {
        JirauTimeEntry::query()
            ->running()
            ->where('user_id', $actor->id)
            ->get()
            ->each(fn (JirauTimeEntry $entry) => $this->close($entry));
    }
}
