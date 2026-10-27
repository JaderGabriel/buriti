@php
    $task = $task ?? null;
    $view = $view ?? 'agenda';
    $month = $month ?? null;
    $returnTo = $returnTo ?? null;
    $statusLabels = $statusLabels ?? [];
    $priorityLabels = $priorityLabels ?? [];
    $projects = $projects ?? collect();
    $contacts = $contacts ?? collect();
    $isDone = $task?->status?->isDone() ?? false;
    $activities = $task?->activities ?? collect();
    $trendActivities = $activities
        ->sortBy(fn ($activity) => [
            optional($activity->happened_at ?? $activity->created_at)->timestamp ?? 0,
            $activity->id,
        ])
        ->values();
@endphp

@if($task)
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-bright">Agenda</p>
            <h2 id="task-edit-title" class="mt-1 font-display text-lg font-semibold text-snow">{{ $task->title }}</h2>
        </div>
        <button type="button" class="rounded-sm border border-line px-2.5 py-1 text-sm text-mist hover:text-snow" data-task-edit-close aria-label="Fechar">✕</button>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('admin.tasks.complete', $task) }}">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            <button type="submit" class="rounded-sm px-3 py-1.5 text-sm font-semibold {{ $isDone ? 'border border-line text-mist hover:text-snow' : 'bg-emerald-600 text-white hover:bg-emerald-500' }}">
                {{ $isDone ? 'Reabrir tarefa' : 'Marcar concluída' }}
            </button>
        </form>
        @if($task->hasMeet())
            <a href="{{ $task->meet_url }}" target="_blank" rel="noopener" class="task-action">
                <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Meet
            </a>
        @elseif($task->want_meet)
            <form method="POST" action="{{ route('admin.tasks.google', $task) }}">
                @csrf
                <input type="hidden" name="return_view" value="{{ $view }}">
                @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
                @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
                <button type="submit" class="task-action">
                    <x-ui.icon name="meet" class="h-3.5 w-3.5" /> Gerar Meet
                </button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.tasks.google', $task) }}">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
            @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
            <button type="submit" class="task-action">
                <x-ui.icon name="calendar" class="h-3.5 w-3.5" /> Agenda
            </button>
        </form>
        @if($task->isSyncedWithGoogle())
            <span class="task-item__sync">Sync</span>
        @endif
        @if($isDone)
            <span class="task-event__done-pill">✓ Concluída</span>
        @endif
    </div>

    <div class="task-event__notes-panel task-event__activities-panel mt-4">
        <div class="task-event__notes-head">
            <p class="task-event__notes-label">Histórico nesta reunião</p>
            @if($task->contact)
                <a href="{{ route('admin.contacts.show', $task->contact) }}#conducao" class="task-event__notes-link">Ver ficha</a>
                <a href="{{ route('admin.contacts.show', ['contact' => $task->contact, 'task_id' => $task->id]) }}#conducao" class="task-event__notes-link">Registrar nota</a>
            @endif
        </div>
        <ol class="task-activity-trend">
            @forelse($trendActivities as $activity)
                <li class="task-activity task-activity--trend task-activity--{{ $activity->type->tone() }}">
                    <span class="task-activity__rail" aria-hidden="true"></span>
                    <span class="task-activity__icon" aria-hidden="true">
                        <x-ui.icon :name="$activity->type->icon()" class="h-3.5 w-3.5" />
                    </span>
                    <div class="min-w-0">
                        <div class="task-activity__top">
                            <span class="task-activity__type">{{ $activity->type->label() }}</span>
                            <time>{{ optional($activity->happened_at ?? $activity->created_at)->format('d/m H:i') }}</time>
                        </div>
                        @if($activity->contact)
                            <a href="{{ route('admin.contacts.activities.edit', [$activity->contact, $activity]) }}" class="task-activity__subject task-activity__subject--link">
                                {{ $activity->subject ?: $activity->type->label() }}
                            </a>
                        @else
                            <p class="task-activity__subject">{{ $activity->subject ?: $activity->type->label() }}</p>
                        @endif
                        @if(filled($activity->body))
                            <p class="task-activity__body">{{ \Illuminate\Support\Str::limit($activity->body, 220) }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="task-event__notes-empty">
                    @if($task->contact)
                        Sem atividades ligadas a este compromisso. Registe na ficha de {{ $task->contact->name }}.
                    @else
                        Sem atividades. Associe um contato e registre a atividade na ficha CRM.
                    @endif
                </li>
            @endforelse
        </ol>
    </div>

    <form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="mt-4 space-y-2">
        @csrf
        @method('PUT')
        <input type="hidden" name="return_view" value="{{ $view }}">
        @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
        <input name="title" value="{{ $task->title }}" required maxlength="180" class="w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm">
        <label class="block text-xs text-mist">
            Nota técnica (opcional)
            <textarea name="description" rows="2" class="mt-1 w-full rounded-sm border border-line bg-panel px-2 py-1.5 text-sm" placeholder="Detalhe interno do compromisso…">{{ $task->description }}</textarea>
        </label>
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
        @include('admin.tasks.partials.google-color-picker', ['task' => $task, 'compact' => true, 'googleEventColors' => $googleEventColors ?? null])
        <label class="flex items-center gap-2 text-xs text-mist">
            <input type="hidden" name="want_meet" value="0">
            <input type="checkbox" name="want_meet" value="1" @checked($task->want_meet) class="rounded border-line">
            Incluir Google Meet (gera e guarda o link no CRM ao sincronizar)
        </label>
        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="submit" class="rounded-sm bg-brand px-3 py-1.5 text-sm font-semibold text-white hover:bg-brand-bright">Salvar</button>
            <button type="button" class="rounded-sm border border-line px-3 py-1.5 text-sm text-mist hover:text-snow" data-task-edit-close>Cancelar</button>
        </div>
    </form>
    <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" class="mt-3" data-confirm="Remover tarefa?">
        @csrf
        @method('DELETE')
        <input type="hidden" name="return_view" value="{{ $view }}">
        @if($month)<input type="hidden" name="return_month" value="{{ $month }}">@endif
        @if($returnTo)<input type="hidden" name="return_to" value="{{ $returnTo }}">@endif
        <button class="text-sm text-red-300">Excluir</button>
    </form>
@endif
