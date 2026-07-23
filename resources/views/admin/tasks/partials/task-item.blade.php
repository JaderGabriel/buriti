@props([
    'task',
    'statusLabels',
    'priorityLabels',
    'projects',
    'contacts',
    'view' => 'calendar',
    'month' => null,
    'compact' => false,
])

@php
    $priorityTone = match ($task->priority->value) {
        'high' => 'task-chip--high',
        'low' => 'task-chip--low',
        default => 'task-chip--medium',
    };
    $statusTone = match ($task->status->value) {
        'doing' => 'task-chip--doing',
        'done' => 'task-chip--done',
        default => 'task-chip--todo',
    };
@endphp

<article class="task-item {{ $compact ? 'task-item--compact' : '' }}" x-data="{ open: false }">
    <div class="task-item__head">
        <button type="button" class="task-item__toggle" @click="open = !open" :aria-expanded="open.toString()">
            <span class="task-item__title">{{ $task->title }}</span>
            <span class="task-item__meta">
                <span class="task-chip {{ $statusTone }}">{{ $task->status->label() }}</span>
                <span class="task-chip {{ $priorityTone }}">{{ $task->priority->label() }}</span>
                @if($task->due_at)
                    <span class="task-item__time">{{ $task->due_at->format('H:i') }}</span>
                @endif
            </span>
        </button>
    </div>

    @unless($compact)
        <p class="task-item__sub">
            {{ $task->project?->name ?? 'Sem projeto' }}
            @if($task->contact) · {{ $task->contact->name }} @endif
            @if($task->due_at) · {{ $task->due_at->format('d/m/Y H:i') }} @endif
        </p>
    @endunless

    <div class="task-item__actions">
        @if($task->hasMeet())
            <a href="{{ $task->meet_url }}" target="_blank" rel="noopener" class="task-action" title="Abrir Meet">
                <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Meet
            </a>
        @elseif($task->want_meet)
            <a href="{{ $task->meetActionUrl() }}" target="_blank" rel="noopener" class="task-action" title="Criar Meet">
                <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Meet
            </a>
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

        <button type="button" class="task-action" @click="open = !open">{{ $compact ? 'Detalhe' : 'Editar' }}</button>
    </div>

    <div x-cloak x-show="open" class="task-item__editor">
        @if($task->description)
            <p class="mb-3 text-xs text-mist">{{ $task->description }}</p>
        @endif
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
                Incluir Google Meet ao sincronizar
            </label>
            <button class="rounded-sm bg-brand px-3 py-1.5 text-sm text-white">Salvar</button>
        </form>
        <div class="mt-3 border-t border-line pt-3">
            <x-admin.attachments-panel
                :attachable="$task"
                type="tasks"
                :kinds="['document']"
                layout="folder"
                title="Pasta de arquivos"
                description="Anexos desta tarefa."
                class="!border-0 !bg-transparent"
            />
        </div>
        <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="mt-3" data-confirm="Remover tarefa?">
            @csrf
            @method('DELETE')
            <input type="hidden" name="return_view" value="{{ $view }}">
            @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
            <button class="text-sm text-red-300">Excluir</button>
        </form>
    </div>
</article>
