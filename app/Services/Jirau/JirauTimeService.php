<?php

namespace App\Services\Jirau;

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
                'message' => 'Pausa em '.$card->key().': '.$mine->elapsedLabel().'.',
            ];
        }

        $this->pauseRunningFor($actor);
        $entry = $this->open($actor, $card, $card->column);

        return [
            'started' => true,
            'entry' => $entry,
            'message' => 'Fazendo '.$card->key().' em «'.$entry->column_name.'».',
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
     *     columns: list<array{name: string, seconds: int, label: string, people: list<array{name: string, seconds: int, label: string}>}>,
     *     entries: Collection<int, JirauTimeEntry>
     * }
     */
    public function snapshot(JirauCard $card): array
    {
        $entries = $card->relationLoaded('timeEntries')
            ? $card->timeEntries
            : $card->timeEntries()->with(['user:id,name,jirau_role,is_admin'])->orderByDesc('started_at')->orderByDesc('id')->get();

        $total = (int) $entries->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());

        $columns = $entries
            ->groupBy(fn (JirauTimeEntry $entry) => (string) ($entry->column_id ?: 'x-'.$entry->column_name))
            ->map(function (Collection $group) {
                $seconds = (int) $group->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());
                $people = $group
                    ->groupBy('user_id')
                    ->map(function (Collection $mine) {
                        $spent = (int) $mine->sum(fn (JirauTimeEntry $entry) => $entry->elapsedSeconds());

                        return [
                            'name' => $mine->first()?->user?->name ?? 'Alguém',
                            'seconds' => $spent,
                            'label' => JirauTimeEntry::formatSeconds($spent),
                        ];
                    })
                    ->sortByDesc('seconds')
                    ->values()
                    ->all();

                return [
                    'name' => $group->first()?->column_name ?: 'Etapa',
                    'seconds' => $seconds,
                    'label' => JirauTimeEntry::formatSeconds($seconds),
                    'people' => $people,
                ];
            })
            ->sortByDesc('seconds')
            ->values()
            ->all();

        return [
            'total' => $total,
            'total_label' => JirauTimeEntry::formatSeconds($total),
            'columns' => $columns,
            'entries' => $entries,
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

    public function pauseRunningFor(User $actor): void
    {
        JirauTimeEntry::query()
            ->running()
            ->where('user_id', $actor->id)
            ->get()
            ->each(fn (JirauTimeEntry $entry) => $this->close($entry));
    }
}
