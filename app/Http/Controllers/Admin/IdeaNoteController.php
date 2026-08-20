<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IdeaNoteColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IdeaNoteRequest;
use App\Http\Requests\Admin\ReorderIdeaNotesRequest;
use App\Http\Requests\Admin\UpdateIdeaNoteColorRequest;
use App\Models\IdeaNote;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class IdeaNoteController extends Controller
{
    public function __construct(private AuditLogger $audit) {}

    public function store(IdeaNoteRequest $request): RedirectResponse
    {
        $nextOrder = ((int) IdeaNote::query()->max('sort_order')) + 1;

        $note = new IdeaNote($request->validated());
        $note->forceFill([
            'color' => $request->validated('color') ?? IdeaNoteColor::Amber->value,
            'user_id' => $request->user()->id,
            'sort_order' => $nextOrder,
        ])->save();

        $this->audit->record('idea_note.created', $note, [
            'summary' => $note->displayTitle(),
        ]);

        return redirect()
            ->to(route('admin.dashboard').'#ideia-'.$note->id)
            ->with('success', 'Ideia adicionada ao mural.');
    }

    public function update(IdeaNoteRequest $request, IdeaNote $ideaNote): RedirectResponse
    {
        $ideaNote->update($request->validated());

        $this->audit->record('idea_note.updated', $ideaNote, [
            'summary' => $ideaNote->displayTitle(),
        ]);

        return redirect()
            ->to(route('admin.dashboard').'#ideia-'.$ideaNote->id)
            ->with('success', 'Ideia atualizada.');
    }

    public function updateColor(UpdateIdeaNoteColorRequest $request, IdeaNote $ideaNote): JsonResponse
    {
        $color = IdeaNoteColor::from($request->validated('color'));
        $from = $ideaNote->color?->value;
        $ideaNote->update(['color' => $color]);

        $this->audit->record('idea_note.color_changed', $ideaNote, [
            'summary' => $ideaNote->displayTitle(),
            'from' => $from,
            'to' => $color->value,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $ideaNote->id,
            'color' => $color->value,
        ]);
    }

    public function reorder(ReorderIdeaNotesRequest $request): JsonResponse
    {
        $ids = array_values(array_unique(array_map('intval', $request->validated('ids'))));
        $total = count($ids);

        DB::transaction(function () use ($ids, $total) {
            foreach ($ids as $index => $id) {
                IdeaNote::query()->whereKey($id)->update([
                    'sort_order' => ($total - $index) * 10,
                ]);
            }
        });

        $this->audit->record('idea_note.reordered', null, [
            'summary' => 'Ordem dos post-its',
            'ids' => $ids,
        ]);

        return response()->json([
            'ok' => true,
            'ids' => $ids,
        ]);
    }

    public function destroy(IdeaNote $ideaNote): RedirectResponse
    {
        $summary = $ideaNote->displayTitle();
        $ideaNote->delete();

        $this->audit->record('idea_note.deleted', null, [
            'summary' => $summary,
            'idea_note_id' => $ideaNote->id,
        ]);

        return redirect()
            ->route('admin.dashboard')
            ->with('success', 'Ideia removida.');
    }
}
