@extends('layouts.admin')

@section('content')
@php
    $defaultDay = now()->format('Y-m-d');
    $defaultDueAt = now()->setTime(9, 0)->format('Y-m-d\TH:i');
@endphp
<div data-task-shell>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-bright">Agenda operacional</p>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Calendário de atividades</h1>
            <p class="mt-1 text-mist">Clique num dia para criar um compromisso — no estilo da Google Agenda.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-bright"
                data-task-day="{{ $defaultDay }}"
                data-task-create
            >
                <x-ui.icon name="task" class="h-4 w-4" />
                Nova tarefa
            </button>
            <a
                href="{{ route('admin.tasks.export', ['month' => $month, 'view' => $view]) }}"
                class="inline-flex items-center gap-2 rounded-sm border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50"
            >
                <x-ui.icon name="download" class="h-4 w-4 text-brand-bright" />
                Exportar agenda
            </a>
            <a
                href="{{ $googleCalendarUrl }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-sm border border-line px-4 py-2 text-sm font-semibold text-snow transition hover:border-brand-bright/50"
            >
                <x-ui.icon name="calendar" class="h-4 w-4 text-brand-bright" />
                Abrir Google
            </a>
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
            <x-admin.inline-docs title="Agenda + lembretes Telegram" class="mt-3">
                <p>Configure URL/embed/API em <a href="{{ route('admin.settings.edit') }}#google-integration">Configurações → Google</a> (documentação ao lado dos campos).</p>
                <p class="admin-docs__note mb-0">
                    Tarefas com prazo recebem aviso no Telegram ~10 min antes para quem as criou.
                    Bot + cron: ver <a href="{{ route('admin.integrations.edit') }}#telegram">Integrações → Telegram</a>.
                </p>
            </x-admin.inline-docs>
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

    @include('admin.tasks.partials.create-dialog', [
        'view' => $view,
        'month' => $month,
        'projects' => $projects,
        'contacts' => $contacts,
        'statusLabels' => $statusLabels,
        'priorityLabels' => $priorityLabels,
        'defaultDueAt' => $defaultDueAt,
    ])
</div>
@endsection
