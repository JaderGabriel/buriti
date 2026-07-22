@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Planejamento de tarefas</h1>
            <p class="mt-1 text-mist">Kanban interno + atalhos para Google Agenda</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <x-ui.button :href="$googleCalendarUrl" variant="secondary" target="_blank" rel="noopener">Abrir Google Agenda</x-ui.button>
            <x-ui.button href="#nova-tarefa">Nova tarefa</x-ui.button>
        </div>
    </div>

    @if($googleCalendarSrc)
        <div class="mb-8">
            <x-admin.calendar-embed :src="$googleCalendarSrc" title="Agenda embutida" />
        </div>
    @else
        <div class="mb-8 rounded-2xl border border-dashed border-line px-5 py-4 text-sm text-mist">
            Configure o embed da Google Agenda em
            <a href="{{ route('admin.settings.edit') }}" class="text-brand-bright hover:underline">Configurações</a>
            (cole a URL do iframe ou o HTML de integração).
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($statusLabels as $status => $label)
            <section class="rounded-2xl border border-line bg-panel/60 p-4">
                <h2 class="mb-4 font-display text-lg font-semibold">{{ $label }}</h2>
                <div class="space-y-3">
                    @forelse($columns[$status] as $task)
                        <article class="rounded-xl border border-line bg-ink/60 p-3" x-data="{ open: false }">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-medium">{{ $task->title }}</h3>
                                <span class="rounded-full bg-white/5 px-2 py-0.5 text-[10px] uppercase tracking-wide text-mist">{{ $task->priority->label() }}</span>
                            </div>
                            @if($task->description)
                                <p class="mt-2 text-xs text-mist">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</p>
                            @endif
                            <p class="mt-2 text-xs text-mist">
                                {{ $task->project?->name ?? 'Sem projeto' }}
                                @if($task->due_at) · {{ $task->due_at->format('d/m H:i') }} @endif
                            </p>
                            <div class="mt-3 flex flex-wrap gap-3">
                                <a href="{{ $task->googleCalendarCreateUrl() }}" target="_blank" rel="noopener" class="text-xs text-brand-bright hover:underline">+ Google Agenda</a>
                                <button type="button" class="text-xs text-mist hover:text-snow" @click="open = !open">Editar</button>
                            </div>
                            <div x-cloak x-show="open" class="mt-3 space-y-2 border-t border-line pt-3">
                                <form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="space-y-2">
                                    @csrf
                                    @method('PUT')
                                    <input name="title" value="{{ $task->title }}" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                    <textarea name="description" rows="2" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">{{ $task->description }}</textarea>
                                    <select name="project_id" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                        <option value="">Sem projeto</option>
                                        @foreach($projects as $project)
                                            <option value="{{ $project->id }}" @selected($task->project_id === $project->id)>{{ $project->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <select name="status" class="rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                            @foreach($statusLabels as $value => $opt)
                                                <option value="{{ $value }}" @selected($task->status->value === $value)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                        <select name="priority" class="rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                            @foreach($priorityLabels as $value => $opt)
                                                <option value="{{ $value }}" @selected($task->priority->value === $value)>{{ $opt }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <input type="datetime-local" name="due_at" value="{{ optional($task->due_at)->format('Y-m-d\TH:i') }}" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                    <button class="rounded-full bg-brand px-3 py-1.5 text-sm text-white">Salvar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.tasks.destroy', $task) }}" data-confirm="Remover tarefa?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-sm text-red-300">Excluir</button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <p class="text-sm text-mist">Vazio</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <section id="nova-tarefa" class="mt-10 max-w-2xl rounded-2xl border border-line bg-panel p-5 sm:p-6">
        <h2 class="font-display text-xl font-semibold">Nova tarefa</h2>
        <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-4 space-y-3">
            @csrf
            <input name="title" required placeholder="Título" class="w-full rounded-xl border border-line bg-ink px-3 py-2.5">
            <textarea name="description" rows="3" placeholder="Descrição" class="w-full rounded-xl border border-line bg-ink px-3 py-2.5"></textarea>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="project_id" class="rounded-xl border border-line bg-ink px-3 py-2.5">
                    <option value="">Sem projeto</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                <input type="datetime-local" name="due_at" class="rounded-xl border border-line bg-ink px-3 py-2.5">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="status" class="rounded-xl border border-line bg-ink px-3 py-2.5">
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-xl border border-line bg-ink px-3 py-2.5">
                    @foreach($priorityLabels as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-ui.button type="submit">Criar tarefa</x-ui.button>
        </form>
    </section>
@endsection
