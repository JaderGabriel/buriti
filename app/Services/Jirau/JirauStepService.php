<?php

namespace App\Services\Jirau;

use App\Enums\JirauCardType;
use App\Models\Jirau;
use App\Models\JirauCard;
use App\Models\JirauColumn;
use App\Models\ProjectStep;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JirauStepService
{
    public function __construct(
        private AuditLogger $audit,
        private JirauBoardService $boards,
    ) {}

    public function createStep(Jirau $jirau, User $actor, array $data): ProjectStep
    {
        $project = $jirau->project;
        abort_unless($project !== null, 404);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Informe o título da etapa.',
            ]);
        }

        return DB::transaction(function () use ($jirau, $project, $actor, $title, $data) {
            $step = $project->steps()->create([
                'title' => $title,
                'notes' => $data['notes'] ?? null,
                'is_completed' => false,
                'completed_at' => null,
                'sort_order' => ((int) $project->steps()->max('sort_order')) + 1,
            ]);

            $cardId = isset($data['card_id']) ? (int) $data['card_id'] : 0;
            if ($cardId > 0) {
                $card = $this->cardOnBoard($jirau, $cardId);
                $this->link($card, $step);
            } elseif (! empty($data['create_card'])) {
                $column = $this->planningColumn($jirau);
                $this->boards->createCard($jirau, $actor, [
                    'title' => $title,
                    'description' => $data['notes'] ?? null,
                    'type' => JirauCardType::Story->value,
                    'column_id' => $column->id,
                    'project_step_id' => $step->id,
                ]);
            }

            $this->audit->record('jirau.step_created', $step, [
                'summary' => $step->title,
                'jirau_id' => $jirau->id,
                'project_id' => $project->id,
            ]);

            return $step;
        });
    }

    public function exportCard(JirauCard $card, User $actor): ProjectStep
    {
        if ($card->project_step_id) {
            return $card->projectStep;
        }

        $jirau = $card->jirau;
        abort_unless($jirau?->project_id, 404);

        return $this->createStep($jirau, $actor, [
            'title' => $card->title,
            'notes' => $card->description,
            'card_id' => $card->id,
        ]);
    }

    public function attach(JirauCard $card, ProjectStep $step): JirauCard
    {
        $this->assertSameProject($card, $step);

        if ($step->jirauCard && (int) $step->jirauCard->id !== (int) $card->id) {
            throw ValidationException::withMessages([
                'step_id' => 'Esta etapa já está junta a outro cartão.',
            ]);
        }

        $this->link($card, $step);
        $this->syncCompletionFromCard($card->fresh(['column', 'projectStep']));

        $this->audit->record('jirau.step_linked', $card, [
            'summary' => $card->title,
            'step_id' => $step->id,
        ]);

        return $card->fresh(['projectStep']);
    }

    public function detach(JirauCard $card): void
    {
        $card->forceFill(['project_step_id' => null])->save();
        $this->audit->record('jirau.step_unlinked', $card, ['summary' => $card->title]);
    }

    public function importUnlinkedSteps(Jirau $jirau, User $actor): int
    {
        $project = $jirau->project;
        abort_unless($project !== null, 404);

        return (int) DB::transaction(function () use ($jirau, $actor, $project) {
            $column = $this->planningColumn($jirau);
            $imported = 0;

            $steps = $project->steps()
                ->whereDoesntHave('jirauCard')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($steps as $step) {
                $this->boards->createCard($jirau, $actor, [
                    'title' => $step->title,
                    'description' => $step->notes,
                    'type' => JirauCardType::Story->value,
                    'column_id' => $step->is_completed
                        ? ($jirau->doneColumn()?->id ?? $column->id)
                        : $column->id,
                    'project_step_id' => $step->id,
                ]);
                $imported++;
            }

            if ($imported > 0) {
                $this->audit->record('jirau.steps_imported', $jirau, [
                    'summary' => $jirau->name,
                    'count' => $imported,
                ]);
            }

            return $imported;
        });
    }

    public function syncCompletionFromCard(JirauCard $card): void
    {
        $step = $card->projectStep;
        if (! $step) {
            return;
        }

        $done = (bool) $card->column?->is_done;
        if ($done && ! $step->is_completed) {
            $step->markCompleted(true);
        }
    }

    public function assertSameProject(JirauCard $card, ProjectStep $step): void
    {
        $projectId = (int) $card->jirau?->project_id;
        abort_unless($projectId > 0 && $projectId === (int) $step->project_id, 404);
    }

    private function link(JirauCard $card, ProjectStep $step): void
    {
        $this->assertSameProject($card, $step);
        $card->forceFill(['project_step_id' => $step->id])->save();
    }

    private function cardOnBoard(Jirau $jirau, int $cardId): JirauCard
    {
        $card = $jirau->cards()->whereKey($cardId)->first();
        if (! $card) {
            abort(404);
        }

        return $card;
    }

    private function planningColumn(Jirau $jirau): JirauColumn
    {
        $column = $jirau->columns
            ->first(fn (JirauColumn $column) => ! $column->is_inbox && ! $column->is_done)
            ?? $jirau->columns()->where('is_inbox', false)->where('is_done', false)->first()
            ?? $jirau->inboxColumn()
            ?? $jirau->columns()->first();

        if (! $column) {
            throw ValidationException::withMessages([
                'title' => 'Este Jirau não tem faixa para receber etapas.',
            ]);
        }

        return $column;
    }
}
