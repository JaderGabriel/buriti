@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Planejamento de tarefas</h1>
            <p class="mt-1 text-mist">Kanban + Google Agenda e Meet integrados</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $instantMeetUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50">
                <x-ui.icon name="meet" class="h-4 w-4 text-brand-bright" />
                Novo Meet
            </a>
            <a href="{{ $googleCalendarUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50">
                <x-ui.icon name="calendar" class="h-4 w-4 text-brand-bright" />
                Google Agenda
            </a>
            <x-ui.button href="#nova-tarefa">Nova tarefa</x-ui.button>
        </div>
    </div>

    <div class="mb-8 grid gap-4 lg:grid-cols-[1.2fr_0.8fr]">
        <div class="rounded-2xl border border-line bg-panel/70 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Integração Google</p>
                    <p class="mt-1 font-display text-lg font-semibold text-snow">{{ $googleIntegration['label'] }}</p>
                </div>
                <span class="rounded-full bg-brand/15 px-3 py-1 text-xs font-semibold text-brand-bright">Nível {{ $googleIntegration['level'] }}/3</span>
            </div>
            <p class="mt-3 text-sm text-mist">{{ $googleIntegration['next_step'] }}</p>
            <div class="mt-4 flex flex-wrap gap-4 text-xs text-mist">
                <span class="{{ $googleIntegration['deep_link'] ? 'text-brand-bright' : '' }}">{{ $googleIntegration['deep_link'] ? '✓' : '○' }} Atalho Agenda</span>
                <span class="{{ $googleIntegration['embed'] ? 'text-brand-bright' : '' }}">{{ $googleIntegration['embed'] ? '✓' : '○' }} Embed</span>
                <span class="{{ $googleIntegration['api'] ? 'text-brand-bright' : '' }}">{{ $googleIntegration['api'] ? '✓' : '○' }} API + Meet auto</span>
            </div>
            <a href="{{ route('admin.settings.edit') }}#google-integration" class="mt-4 inline-flex text-sm font-semibold text-brand-bright hover:underline">Configurar integração →</a>
        </div>

        @if($googleCalendarSrc)
            <div class="overflow-hidden rounded-2xl border border-line">
                <x-admin.calendar-embed :src="$googleCalendarSrc" title="Agenda embutida" />
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-line px-5 py-6 text-sm text-mist">
                Sem embed da Agenda. Publique o calendário no Google e cole a URL em
                <a href="{{ route('admin.settings.edit') }}#google-integration" class="text-brand-bright hover:underline">Configurações</a>.
            </div>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        @foreach ($statusLabels as $status => $label)
            <section class="rounded-2xl border border-line bg-panel/60 p-4">
                <h2 class="mb-4 font-display text-lg font-semibold">{{ $label }}</h2>
                <div class="space-y-3">
                    @forelse($columns[$status] as $task)
                        <article class="rounded-xl border border-line bg-ink/60 p-3" x-data="({ editing: false })">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="font-medium">{{ $task->title }}</h3>
                                <span class="rounded-full bg-white/5 px-2 py-0.5 text-[10px] uppercase tracking-wide text-mist">{{ $task->priority->label() }}</span>
                            </div>
                            @if($task->description)
                                <p class="mt-2 text-xs text-mist">{{ \Illuminate\Support\Str::limit($task->description, 100) }}</p>
                            @endif
                            <p class="mt-2 text-xs text-mist">
                                {{ $task->project?->name ?? 'Sem projeto' }}
                                @if($task->contact) · {{ $task->contact->name }} @endif
                                @if($task->due_at) · {{ $task->due_at->format('d/m H:i') }} @endif
                            </p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                @if($task->hasMeet())
                                    <a href="{{ $task->meet_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-brand/40 bg-brand/10 px-2.5 py-1 text-xs font-semibold text-brand-bright hover:bg-brand/20" title="Abrir Meet">
                                        <x-ui.icon name="meet" class="h-3.5 w-3.5" />
                                        Meet
                                    </a>
                                @elseif($task->want_meet)
                                    <a href="{{ $task->meetActionUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full border border-line px-2.5 py-1 text-xs font-semibold text-mist hover:border-brand-bright/50 hover:text-brand-bright" title="Criar Meet agora">
                                        <x-ui.icon name="meet" class="h-3.5 w-3.5" />
                                        Meet
                                    </a>
                                @endif

                                <form method="POST" action="{{ route('admin.tasks.google', $task) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-line px-2.5 py-1 text-xs font-semibold text-mist transition hover:border-brand-bright/50 hover:text-brand-bright" title="Sincronizar / criar no Google Agenda">
                                        <x-ui.icon name="calendar" class="h-3.5 w-3.5" />
                                        Agenda
                                    </button>
                                </form>

                                @if($task->isSyncedWithGoogle())
                                    <span class="text-[10px] uppercase tracking-wide text-brand-bright">Sync</span>
                                @endif

                                <button type="button" class="text-xs text-mist hover:text-snow" @click="editing = !editing">Editar</button>
                            </div>
                            <div x-cloak x-show="editing" class="mt-3 space-y-2 border-t border-line pt-3">
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
                                    <select name="contact_id" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                        <option value="">Sem contato CRM</option>
                                        @foreach($contacts as $contact)
                                            <option value="{{ $contact->id }}" @selected($task->contact_id === $contact->id)>{{ $contact->name }}</option>
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
                                    <input type="url" name="meet_url" value="{{ $task->meet_url }}" placeholder="URL do Google Meet" class="w-full rounded-lg border border-line bg-panel px-2 py-1.5 text-sm">
                                    <label class="flex items-center gap-2 text-xs text-mist">
                                        <input type="hidden" name="want_meet" value="0">
                                        <input type="checkbox" name="want_meet" value="1" @checked($task->want_meet) class="rounded border-line">
                                        Incluir Google Meet ao sincronizar
                                    </label>
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
                <select name="contact_id" class="rounded-xl border border-line bg-ink px-3 py-2.5">
                    <option value="">Sem contato CRM</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                    @endforeach
                </select>
            </div>
            <input type="datetime-local" name="due_at" class="w-full rounded-xl border border-line bg-ink px-3 py-2.5">
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
            <input type="url" name="meet_url" placeholder="URL do Google Meet (opcional)" class="w-full rounded-xl border border-line bg-ink px-3 py-2.5">
            <label class="flex items-center gap-2 text-sm text-mist">
                <input type="hidden" name="want_meet" value="0">
                <input type="checkbox" name="want_meet" value="1" checked class="rounded border-line">
                Preparar com Google Meet (ícone Meet + sync Agenda)
            </label>
            <x-ui.button type="submit">Criar tarefa</x-ui.button>
        </form>
    </section>
@endsection
