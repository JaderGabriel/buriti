<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IdeaNoteColor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IdeaNoteRequest;
use App\Models\IdeaNote;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

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
