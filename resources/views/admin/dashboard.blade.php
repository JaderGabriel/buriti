@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Dashboard</h1>
        <p class="mt-1 text-mist">Visão rápida da operação BURI-TI</p>
    </div>

    <div class="grid gap-4 grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['Mensagens novas', $unreadMessages, route('admin.messages.index')],
            ['Total mensagens', $totalMessages, route('admin.messages.index')],
            ['Projetos', $projectsCount, route('admin.projects.index')],
            ['Tarefas abertas', $openTasks, route('admin.tasks.index')],
        ] as [$label, $value, $href])
            <a href="{{ $href }}" class="rounded-2xl border border-line bg-panel p-4 transition hover:border-brand/50 sm:p-5">
                <p class="text-xs text-mist sm:text-sm">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-bold text-brand-bright sm:text-3xl">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-line bg-panel p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold">Mensagens recentes</h2>
                <a href="{{ route('admin.messages.index') }}" class="text-sm text-brand-bright">Ver todas</a>
            </div>
            <ul class="space-y-3">
                @forelse($recentMessages as $message)
                    <li>
                        <a href="{{ route('admin.messages.show', $message) }}" class="block rounded-xl border border-transparent px-2 py-2 hover:border-line hover:bg-ink/40">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium {{ $message->isUnread() ? 'text-snow' : 'text-mist' }}">{{ $message->name }}</p>
                                <span class="shrink-0 text-xs text-mist">{{ $message->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="truncate text-sm text-mist">{{ $message->subject }}</p>
                        </a>
                    </li>
                @empty
                    <li class="text-sm text-mist">Nenhuma mensagem ainda.</li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-2xl border border-line bg-panel p-5">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="font-display text-lg font-semibold">Próximas tarefas</h2>
                <a href="{{ route('admin.tasks.index') }}" class="text-sm text-brand-bright">Planejamento</a>
            </div>
            <ul class="space-y-3">
                @forelse($upcomingTasks as $task)
                    <li class="rounded-xl border border-line/70 px-3 py-2">
                        <p class="font-medium">{{ $task->title }}</p>
                        <p class="text-xs text-mist">
                            {{ $task->project?->name ?? 'Sem projeto' }}
                            @if($task->due_at) · {{ $task->due_at->format('d/m/Y H:i') }} @endif
                        </p>
                    </li>
                @empty
                    <li class="text-sm text-mist">Nenhuma tarefa aberta.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="mt-8">
        <x-admin.calendar-embed :src="$googleCalendarSrc" />
    </div>
@endsection
