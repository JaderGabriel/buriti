@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-bright">Agenda operacional</p>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Calendário de atividades</h1>
            <p class="mt-1 text-mist">Planeje entregas por data, prioridade e status — com Meet e Google Agenda.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ $instantMeetUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-sm border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50">
                <x-ui.icon name="meet" class="h-4 w-4 text-brand-bright" />
                Novo Meet
            </a>
            <a href="{{ $googleCalendarUrl }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-sm border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50">
                <x-ui.icon name="calendar" class="h-4 w-4 text-brand-bright" />
                Google Agenda
            </a>
            <x-ui.button href="#nova-tarefa">Nova atividade</x-ui.button>
        </div>
    </div>

    <div class="task-stats mb-6">
        <div class="task-stat">
            <span class="task-stat__value">{{ $stats['total'] }}</span>
            <span class="task-stat__label">Total</span>
        </div>
        <div class="task-stat">
            <span class="task-stat__value">{{ $stats['open'] }}</span>
            <span class="task-stat__label">Em aberto</span>
        </div>
        <div class="task-stat">
            <span class="task-stat__value">{{ $stats['due_month'] }}</span>
            <span class="task-stat__label">Neste mês</span>
        </div>
        <div class="task-stat">
            <span class="task-stat__value">{{ $stats['undated'] }}</span>
            <span class="task-stat__label">Sem data</span>
        </div>
    </div>

    <div class="mb-6 flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        @include('admin.tasks.partials.view-switcher')

        <div class="min-w-0 flex-1 xl:max-w-md">
            @if($googleCalendarSrc)
                <div class="overflow-hidden rounded-sm border border-line">
                    <x-admin.calendar-embed :src="$googleCalendarSrc" title="Google Agenda" />
                </div>
            @else
                <div class="rounded-sm border border-dashed border-line px-4 py-4 text-sm text-mist">
                    <p class="font-medium text-snow">{{ $googleIntegration['label'] }}</p>
                    <p class="mt-1">{{ $googleIntegration['next_step'] }}</p>
                    <a href="{{ route('admin.settings.edit') }}#google-integration" class="mt-2 inline-flex text-sm font-semibold text-brand-bright hover:underline">Configurar integração →</a>
                </div>
            @endif
        </div>
    </div>

    @if($view === 'calendar')
        @include('admin.tasks.partials.calendar')
    @elseif($view === 'agenda')
        @include('admin.tasks.partials.agenda')
    @elseif($view === 'board')
        @include('admin.tasks.partials.board')
    @else
        @include('admin.tasks.partials.list')
    @endif

    <section id="nova-tarefa" class="mt-10 max-w-2xl rounded-sm border border-line bg-panel p-5 sm:p-6">
        <h2 class="font-display text-xl font-semibold">Nova atividade</h2>
        <p class="mt-1 text-sm text-mist">Defina data/hora para aparecer no calendário e na agenda.</p>
        <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            <input type="hidden" name="return_month" value="{{ $month }}">
            <input name="title" required placeholder="Título da atividade" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5">
            <textarea name="description" rows="3" placeholder="Descrição" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5"></textarea>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="project_id" class="rounded-sm border border-line bg-ink px-3 py-2.5">
                    <option value="">Sem projeto</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}">{{ $project->name }}</option>
                    @endforeach
                </select>
                <select name="contact_id" class="rounded-sm border border-line bg-ink px-3 py-2.5">
                    <option value="">Sem contato CRM</option>
                    @foreach($contacts as $contact)
                        <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="block text-sm text-mist">
                Data e hora
                <input type="datetime-local" name="due_at" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow">
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <select name="status" class="rounded-sm border border-line bg-ink px-3 py-2.5">
                    @foreach($statusLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <select name="priority" class="rounded-sm border border-line bg-ink px-3 py-2.5">
                    @foreach($priorityLabels as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <input type="url" name="meet_url" placeholder="URL do Google Meet (opcional)" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5">
            <label class="flex items-center gap-2 text-sm text-mist">
                <input type="hidden" name="want_meet" value="0">
                <input type="checkbox" name="want_meet" value="1" checked class="rounded border-line">
                Preparar com Google Meet (ícone Meet + sync Agenda)
            </label>
            <x-ui.button type="submit">Criar atividade</x-ui.button>
        </form>
    </section>
@endsection
