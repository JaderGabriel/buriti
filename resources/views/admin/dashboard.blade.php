@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Dashboard</h1>
        <p class="mt-1 text-mist">Visão rápida da operação BURI-TI</p>
    </div>

    <x-admin.crm-journey class="mb-5" />

    <x-admin.crm-funnel
        :counts="$opportunityStageCounts"
        :filter-base="route('admin.opportunities.index', ['view' => 'board'])"
        class="mb-6"
    />

    <div class="grid gap-4 grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        @foreach ([
            ['Mensagens novas', $unreadMessages, route('admin.messages.index')],
            ['Total mensagens', $totalMessages, route('admin.messages.index')],
            ['Contatos CRM', $contactsCount, route('admin.contacts.index')],
            ['Oportunidades abertas', $openOpportunities, route('admin.opportunities.index')],
            ['Projetos', $projectsCount, route('admin.projects.index')],
            ['Tarefas abertas', $openTasks, route('admin.tasks.index')],
        ] as [$label, $value, $href])
            <a href="{{ $href }}" class="rounded-2xl border border-line bg-panel p-4 transition hover:border-brand/50 sm:p-5">
                <p class="text-xs text-mist sm:text-sm">{{ $label }}</p>
                <p class="mt-2 font-display text-2xl font-bold text-brand-bright sm:text-3xl">{{ $value }}</p>
            </a>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
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
                <h2 class="font-display text-lg font-semibold">Contatos recentes</h2>
                <a href="{{ route('admin.contacts.index') }}" class="text-sm text-brand-bright">Contatos</a>
            </div>
            <ul class="space-y-3">
                @forelse($recentContacts as $contact)
                    <li>
                        <a href="{{ route('admin.contacts.show', $contact) }}" class="block rounded-xl border border-transparent px-2 py-2 hover:border-line hover:bg-ink/40">
                            <p class="font-medium text-snow">{{ $contact->name }}</p>
                            <p class="truncate text-sm text-mist">{{ $contact->companyLabel() ?: ($contact->email ?? $contact->status->label()) }}</p>
                        </a>
                    </li>
                @empty
                    <li class="text-sm text-mist">Nenhum contato ainda.</li>
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
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-bright">Espaço livre</p>
                <h2 class="font-display text-xl font-semibold sm:text-2xl">Ideias & rascunhos</h2>
                <p class="mt-1 text-sm text-mist">Post-its para anotar ideias — nenhum campo é obrigatório.</p>
            </div>
            <form method="POST" action="{{ route('admin.idea-notes.store') }}">
                @csrf
                <input type="hidden" name="color" value="amber">
                <button type="submit" class="inline-flex items-center gap-2 rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-bright">
                    + Novo post-it
                </button>
            </form>
        </div>

        <div class="postit-board idea-board grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @forelse($ideaNotes as $index => $note)
                @include('admin.dashboard.partials.idea-note', [
                    'note' => $note,
                    'index' => $index,
                    'ideaColors' => $ideaColors,
                ])
            @empty
                <div class="col-span-full rounded-sm border border-dashed border-line px-6 py-12 text-center text-sm text-mist">
                    <p>Nenhuma ideia ainda. Crie um post-it e use como quiser.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-8">
        <x-admin.calendar-embed :src="$googleCalendarSrc" />
    </div>
@endsection
