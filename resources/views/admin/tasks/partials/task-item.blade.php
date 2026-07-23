@php
    $task = $task ?? null;
    $statusLabels = $statusLabels ?? [];
    $priorityLabels = $priorityLabels ?? [];
    $projects = $projects ?? collect();
    $contacts = $contacts ?? collect();
    $view = $view ?? 'calendar';
    $month = $month ?? null;
    $compact = (bool) ($compact ?? false);

    $summaryParts = array_values(array_filter([
        $task?->project?->name,
        $task?->contact?->name,
        $task?->status?->label(),
    ]));
    $summary = implode(' · ', $summaryParts);
    $time = $task?->due_at?->format('H:i');
@endphp

@if($task)
<article
    class="task-event {{ $compact ? 'task-event--compact' : 'task-event--agenda' }} task-event--{{ $task->status->value }}"
    data-task-event
    x-data="{ open: false }"
>
    <button
        type="button"
        class="task-event__hit"
        @click="open = !open"
        :aria-expanded="open.toString()"
        title="{{ $task->title }}"
    >
        @if($time)
            <span class="task-event__time">{{ $time }}</span>
        @endif
        <span class="task-event__main">
            <span class="task-event__title">{{ $task->title }}</span>
            @if($summary !== '')
                <span class="task-event__summary">{{ $summary }}</span>
            @elseif($task->description)
                <span class="task-event__summary">{{ \Illuminate\Support\Str::limit($task->description, $compact ? 42 : 80) }}</span>
            @endif
        </span>
    </button>

    <div x-cloak x-show="open" class="task-event__editor" @click.stop>
        <div class="task-event__toolbar">
            @if($task->hasMeet())
                <a href="{{ $task->meet_url }}" target="_blank" rel="noopener" class="task-action" title="Abrir Meet">
                    <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Meet
                </a>
            @elseif($task->want_meet)
                <form method="POST" action="{{ route('admin.tasks.google', $task) }}" class="inline">
                    @csrf
                    <input type="hidden" name="return_view" value="{{ $view }}">
                    @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
                    <button type="submit" class="task-action" title="Gerar Meet via API no CRM">
                        <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Gerar Meet
                    </button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.tasks.google', $task) }}" class="inline">
                @csrf
                <input type="hidden" name="return_view" value="{{ $view }}">
                @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
                <button type="submit" class="task-action" title="Sincronizar Agenda">
                    <x-ui.icon name="calendar" class="h-3.5 w-3.5" /> Agenda
                </button>
            </form>

            @if($task->isSyncedWithGoogle())
                <span class="task-item__sync">Sync</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="space-y-2">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_view" value="{{ $view }}">
            @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
            <input name="title" value="{{ $task->title }}" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
            <textarea name="description" rows="2" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">{{ $task->description }}</textarea>
            <select name="project_id" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
                <option value="">Sem projeto</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected($task->project_id === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            <select name="contact_id" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
                <option value="">Sem contato CRM</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected($task->contact_id === $contact->id)>{{ $contact->name }}</option>
                @endforeach
            </select>
            <div class="grid grid-cols-2 gap-2">
                <select name="status" class="rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
                    @foreach($statusLabels as $value => $opt)
                        <option value="{{ $value }}" @selected($task->status->value === $value)>{{ $opt }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
                    @foreach($priorityLabels as $value => $opt)
                        <option value="{{ $value }}" @selected($task->priority->value === $value)>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <input type="datetime-local" name="due_at" value="{{ optional($task->due_at)->format('Y-m-d\TH:i') }}" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
            <input type="url" name="meet_url" value="{{ $task->meet_url }}" placeholder="URL do Google Meet" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
            <label class="flex items-center gap-2 text-xs text-mist">
                <input type="hidden" name="want_meet" value="0">
                <input type="checkbox" name="want_meet" value="1" @checked($task->want_meet) class="rounded border-line">
                Incluir Google Meet (gera e guarda o link no CRM ao sincronizar)
            </label>
            <button class="rounded-sm bg-brand px-3 py-1.5 text-sm text-white">Salvar</button>
        </form>
        <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="mt-3" data-confirm="Remover tarefa?">
            @csrf
            @method('DELETE')
            <input type="hidden" name="return_view" value="{{ $view }}">
            @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
            <button class="text-sm text-red-300">Excluir</button>
        </form>
    </div>
</article>
@endif
