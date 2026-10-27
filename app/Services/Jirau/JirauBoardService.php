<?php

namespace App\Services\Jirau;

use App\Enums\JirauCardPriority;
use App\Enums\JirauCardType;
use App\Enums\JirauTemplate;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\Project;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JirauBoardService
{
    public function __construct(
        private AuditLogger $audit,
        private JirauNotifier $notifier,
    ) {}

    public function createForProject(
        Project $project,
        User $actor,
        ?string $name = null,
        JirauTemplate $template = JirauTemplate::Development,
    ): Jirau {
        return DB::transaction(function () use ($project, $actor, $name, $template) {
            $jirau = Jirau::query()->create([
                'project_id' => $project->id,
                'name' => $name ?: ($template->label().' · '.$project->name),
                'template' => $template,
                'created_by' => $actor->id,
            ]);

            $this->provisionDefaults($jirau, $template);

            $this->audit->record('jirau.created', $jirau, [
                'summary' => $jirau->name,
                'project_id' => $project->id,
                'template' => $template->value,
            ]);

            return $jirau->fresh(['columns', 'labels', 'project']);
        });
    }

    public function rename(Jirau $jirau, string $name): Jirau
    {
        $from = $jirau->name;
        $jirau->forceFill(['name' => $name])->save();

        $this->audit->record('jirau.renamed', $jirau, [
            'summary' => $name,
            'from' => $from,
        ]);

        return $jirau;
    }

    public function provisionDefaults(Jirau $jirau, ?JirauTemplate $template = null): void
    {
        $template ??= $jirau->template ?? JirauTemplate::Development;

        foreach ($template->columns() as $index => $column) {
            $jirau->columns()->create([
                ...$column,
                'sort_order' => ($index + 1) * 10,
            ]);
        }

        foreach ($template->labels() as $label) {
            $jirau->labels()->create($label);
        }
    }

    public function createDemand(Jirau $jirau, User $actor, array $data): JirauCard
    {
        $inbox = $jirau->inboxColumn();
        if (! $inbox) {
            throw ValidationException::withMessages([
                'title' => 'Este Jirau não tem faixa de entrada para demandas.',
            ]);
        }

        $card = $this->createCard($jirau, $actor, [
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? JirauCardPriority::Medium->value,
            'type' => JirauCardType::Demand->value,
            'column_id' => $inbox->id,
            'assigned_to' => null,
            'estimate' => null,
        ], notify: false);

        $this->notifier->demandCreated($jirau, $card, $actor);

        return $card;
    }

    public function createCard(Jirau $jirau, User $actor, array $data, bool $notify = true): JirauCard
    {
        $column = $this->resolveColumn($jirau, $data['column_id'] ?? null);
        $stepId = isset($data['project_step_id']) ? (int) $data['project_step_id'] : null;
        if ($stepId) {
            $owned = $jirau->project?->steps()->whereKey($stepId)->doesntHave('jirauCard')->exists();
            if (! $owned) {
                throw ValidationException::withMessages([
                    'title' => 'Etapa inválida ou já junta a um cartão.',
                ]);
            }
        }
        $max = (int) $jirau->cards()->where('column_id', $column->id)->max('sort_order');
        $due = $data['due_at'] ?? null;
        if ($due === null || $due === '') {
            $due = $column->dueFromDefault();
        }

        $card = $jirau->cards()->create([
            'column_id' => $column->id,
            'created_by' => $actor->id,
            'assigned_to' => $this->resolveAssignee($data['assigned_to'] ?? null),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'type' => $data['type'] ?? JirauCardType::Task->value,
            'priority' => $data['priority'] ?? JirauCardPriority::Medium->value,
            'estimate' => $data['estimate'] ?? null,
            'due_at' => $due,
            'sort_order' => $max + 10,
            'triaged_at' => $column->is_inbox ? null : now(),
        ]);
        if ($stepId) {
            $card->forceFill(['project_step_id' => $stepId])->save();
        }

        if (! empty($data['label_ids']) && is_array($data['label_ids'])) {
            $card->labels()->sync($this->ownedLabelIds($jirau, $data['label_ids']));
        }

        $this->audit->record('jirau.card_created', $card, [
            'summary' => $card->title,
            'jirau_id' => $jirau->id,
            'type' => $card->type->value,
        ]);

        $created = $card->fresh(['labels', 'column.watchers', 'creator', 'assignee']);
        if ($notify) {
            $this->notifier->cardCreated($jirau, $created, $actor);
        }

        return $created;
    }

    public function updateCard(JirauCard $card, User $actor, array $data): JirauCard
    {
        $admin = $actor->is_admin;

        $payload = [
            'title' => $data['title'] ?? $card->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $card->description,
        ];

        if ($admin) {
            if (array_key_exists('type', $data) && $data['type'] !== null) {
                $payload['type'] = $data['type'];
            }
            if (array_key_exists('priority', $data) && $data['priority'] !== null) {
                $payload['priority'] = $data['priority'];
            }
            if (array_key_exists('due_at', $data)) {
                $payload['due_at'] = $data['due_at'];
            }
            if (array_key_exists('estimate', $data)) {
                $payload['estimate'] = $data['estimate'];
            }
            if (array_key_exists('assigned_to', $data)) {
                $payload['assigned_to'] = $this->resolveAssignee($data['assigned_to']);
            }
        } else {
            if (! $card->isInbox() || (int) $card->created_by !== (int) $actor->id) {
                throw ValidationException::withMessages([
                    'title' => 'Só é possível editar a própria demanda enquanto ela estiver em triagem.',
                ]);
            }
            if (array_key_exists('priority', $data) && $data['priority'] !== null) {
                $payload['priority'] = $data['priority'];
            }
        }

        $snapshot = [
            'title' => $card->title,
            'priority' => $card->priority->value,
            'type' => $card->type->value,
            'estimate' => $card->estimate,
            'assigned_to' => $card->assigned_to,
            'due_at' => $card->due_at?->timestamp,
        ];

        $card->update($payload);

        if ($admin && array_key_exists('label_ids', $data)) {
            $card->labels()->sync($this->ownedLabelIds($card->jirau, $data['label_ids'] ?? []));
        }

        $fresh = $card->fresh(['labels', 'column.watchers', 'creator', 'assignee', 'jirau.project']);
        if ($fresh->due_at?->timestamp !== $snapshot['due_at']) {
            $fresh->forceFill(['telegram_reminder_sent_at' => null])->save();
        }

        $this->audit->record('jirau.card_updated', $card, [
            'summary' => $card->title,
            'jirau_id' => $card->jirau_id,
        ]);

        $this->notifier->cardUpdated($fresh->jirau, $fresh, $actor, $this->updateSummary($snapshot, $fresh));

        return $fresh;
    }

    public function moveCard(JirauCard $card, User $actor, int $columnId, array $orderedIds = []): JirauCard
    {
        if (! $actor->is_admin) {
            throw ValidationException::withMessages([
                'column_id' => 'A posição no quadro é definida pelo admin do Jirau.',
            ]);
        }

        $column = $this->resolveColumn($card->jirau, $columnId);
        $from = $card->column;
        $fromInbox = $card->isInbox();

        $card->column_id = $column->id;
        if ($fromInbox && ! $column->is_inbox && $card->triaged_at === null) {
            $card->triaged_at = now();
        }
        $arrived = ! $from || (int) $from->id !== (int) $column->id;
        if ($arrived) {
            $column->applyDefaultDueTo($card);
        }
        $card->save();

        $ids = array_values(array_unique(array_map('intval', $orderedIds)));
        if ($ids !== [] && ! in_array((int) $card->id, $ids, true)) {
            $ids[] = (int) $card->id;
        }

        if ($ids === []) {
            $ids = $column->cards()->whereKeyNot($card->id)->pluck('id')->all();
            $ids[] = (int) $card->id;
        }

        foreach ($ids as $index => $id) {
            JirauCard::query()
                ->whereKey($id)
                ->where('jirau_id', $card->jirau_id)
                ->update([
                    'column_id' => $column->id,
                    'sort_order' => ($index + 1) * 10,
                ]);
        }

        $this->audit->record('jirau.card_moved', $card, [
            'summary' => $card->title,
            'column' => $column->slug,
        ]);

        $moved = $card->fresh(['column.watchers', 'assignee', 'jirau.project']);
        if ($from && (int) $from->id !== (int) $column->id) {
            $this->notifier->cardMoved(
                $moved->jirau,
                $moved,
                $actor,
                $from,
                $column,
                $fromInbox && ! $column->is_inbox,
            );
        }

        return $moved;
    }

    public function archiveCard(JirauCard $card, User $actor): void
    {
        if (! $actor->is_admin) {
            abort(403);
        }

        $card->loadMissing(['column.watchers', 'assignee', 'jirau.project']);
        $card->update(['archived_at' => now()]);
        $this->audit->record('jirau.card_archived', $card, ['summary' => $card->title]);
        $this->notifier->cardArchived($card->jirau, $card, $actor);
    }

    /** @return list<int> */
    public static function estimateOptions(): array
    {
        return [1, 2, 3, 5, 8, 13];
    }

    private function resolveAssignee(mixed $userId): ?int
    {
        if ($userId === null || $userId === '') {
            return null;
        }

        $id = (int) $userId;
        $ok = User::assignableOnJirau()->whereKey($id)->exists();
        if (! $ok) {
            throw ValidationException::withMessages([
                'assigned_to' => 'Só é possível atribuir a alguém da equipe de TI.',
            ]);
        }

        return $id;
    }

    private function resolveColumn(Jirau $jirau, mixed $columnId): JirauColumn
    {
        $column = $jirau->columns()->whereKey($columnId)->first();
        if (! $column) {
            throw ValidationException::withMessages([
                'column_id' => 'Faixa inválida neste Jirau.',
            ]);
        }

        return $column;
    }

    /**
     * @param  array{title: string, priority: string, type: string, estimate: ?int, assigned_to: ?int, due_at: ?int}  $before
     * @return list<string>
     */
    private function updateSummary(array $before, JirauCard $after): array
    {
        $lines = [];

        if ($after->title !== $before['title']) {
            $lines[] = 'Título alterado';
        }
        if ($after->priority->value !== $before['priority']) {
            $lines[] = 'Prioridade: '.$after->priority->label();
        }
        if ($after->type->value !== $before['type']) {
            $lines[] = 'Tipo: '.$after->type->label();
        }
        if ((int) $after->estimate !== (int) $before['estimate']) {
            $lines[] = 'Estimativa: '.($after->estimate ? $after->estimate.' pts' : '—');
        }
        if ((int) $after->assigned_to !== (int) $before['assigned_to']) {
            $lines[] = 'Responsável: '.($after->assignee?->name ?? 'sem alocação');
        }
        if ($after->due_at?->timestamp !== $before['due_at']) {
            $due = $after->due_at?->timezone(config('app.timezone'))?->format('d/m/Y H:i');
            $lines[] = 'Prazo: '.($due ?: 'removido');
        }

        return $lines;
    }

    /** @param  list<mixed>  $ids */
    private function ownedLabelIds(Jirau $jirau, array $ids): array
    {
        $wanted = array_values(array_unique(array_map('intval', $ids)));

        return $jirau->labels()->whereKey($wanted)->pluck('id')->all();
    }
}
