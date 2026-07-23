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

    <div class="dash-panels mt-8">
        <section class="dash-panel dash-panel--messages">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="message" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Inbox</p>
                        <h2 class="dash-panel__title">Mensagens</h2>
                    </div>
                </div>
                <div class="dash-panel__meta">
                    @if($unreadMessages > 0)
                        <span class="dash-panel__pill dash-panel__pill--alert">{{ $unreadMessages }} nova{{ $unreadMessages === 1 ? '' : 's' }}</span>
                    @else
                        <span class="dash-panel__pill">Em dia</span>
                    @endif
                    <a href="{{ route('admin.messages.index') }}" class="dash-panel__link">Abrir</a>
                </div>
            </header>

            <ul class="dash-feed">
                @forelse($recentMessages as $message)
                    @php
                        $channel = match ($message->preferred_channel) {
                            'whatsapp' => 'WhatsApp',
                            'phone' => 'Telefone',
                            'email' => 'E-mail',
                            default => null,
                        };
                    @endphp
                    <li>
                        <a href="{{ route('admin.messages.show', $message) }}" @class(['dash-feed__item', 'is-unread' => $message->isUnread()])>
                            <span class="dash-feed__rail" aria-hidden="true"></span>
                            <div class="dash-feed__body">
                                <div class="dash-feed__row">
                                    <p class="dash-feed__name">{{ $message->name }}</p>
                                    <time class="dash-feed__time">{{ $message->created_at->diffForHumans() }}</time>
                                </div>
                                <p class="dash-feed__subject">{{ $message->subject }}</p>
                                <p class="dash-feed__preview">{{ \Illuminate\Support\Str::limit(strip_tags((string) $message->message), 90) }}</p>
                                <div class="dash-feed__tags">
                                    @if($message->isUnread())
                                        <span class="dash-tag dash-tag--new">Não lida</span>
                                    @endif
                                    @if($channel)
                                        <span class="dash-tag">{{ $channel }}</span>
                                    @endif
                                    @if($message->company)
                                        <span class="dash-tag">{{ $message->company }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="dash-feed__empty">
                        <p>Nenhuma mensagem ainda.</p>
                        <p class="dash-feed__empty-hint">Quando alguém escrever no site, aparece aqui e gera um lead.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="dash-panel dash-panel--contacts">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="contact" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">CRM</p>
                        <h2 class="dash-panel__title">Contatos</h2>
                    </div>
                </div>
                <div class="dash-panel__meta">
                    <span class="dash-panel__pill">{{ $leadsCount }} lead{{ $leadsCount === 1 ? '' : 's' }}</span>
                    <a href="{{ route('admin.contacts.index') }}" class="dash-panel__link">Agenda</a>
                </div>
            </header>

            <ul class="dash-feed">
                @forelse($recentContacts as $contact)
                    @php
                        $initials = collect(preg_split('/\s+/', trim($contact->name)) ?: [])
                            ->filter()
                            ->take(2)
                            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                            ->implode('');
                        $phone = \App\Support\PhoneNumber::format($contact->phone);
                    @endphp
                    <li>
                        <a href="{{ route('admin.contacts.show', $contact) }}" class="dash-feed__item dash-feed__item--contact">
                            <span class="dash-feed__avatar" data-tone="{{ $contact->status->tone() }}">{{ $initials ?: '?' }}</span>
                            <div class="dash-feed__body">
                                <div class="dash-feed__row">
                                    <p class="dash-feed__name">{{ $contact->name }}</p>
                                    <x-admin.crm-badge :status="$contact->status" compact />
                                </div>
                                <p class="dash-feed__subject">
                                    {{ $contact->companyLabel() ?: 'Sem empresa' }}
                                    @if($contact->role) · {{ $contact->role }} @endif
                                </p>
                                <div class="dash-feed__tags">
                                    @if($phone)
                                        <span class="dash-tag dash-tag--phone">{{ $phone }}</span>
                                    @elseif($contact->email)
                                        <span class="dash-tag">{{ $contact->email }}</span>
                                    @endif
                                    <span class="dash-tag">{{ $contact->source->label() }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="dash-feed__empty">
                        <p>Nenhum contato ainda.</p>
                        <p class="dash-feed__empty-hint">Leads do site e cadastros manuais entram na agenda telefónica.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="dash-panel dash-panel--tasks">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="task" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Agenda</p>
                        <h2 class="dash-panel__title">Próximas tarefas</h2>
                    </div>
                </div>
                <div class="dash-panel__meta">
                    @if($tasksDueSoon > 0)
                        <span class="dash-panel__pill dash-panel__pill--warn">{{ $tasksDueSoon }} em 24h</span>
                    @else
                        <span class="dash-panel__pill">{{ $openTasks }} aberta{{ $openTasks === 1 ? '' : 's' }}</span>
                    @endif
                    <a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="dash-panel__link">Planejar</a>
                </div>
            </header>

            <ul class="dash-feed">
                @forelse($upcomingTasks as $task)
                    @php $urgency = $task->dueUrgency(); @endphp
                    <li>
                        <a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="dash-feed__item dash-feed__item--task" data-urgency="{{ $urgency }}">
                            <span class="dash-feed__due" aria-hidden="true">
                                @if($task->due_at)
                                    <strong>{{ $task->due_at->format('d') }}</strong>
                                    <small>{{ $task->due_at->translatedFormat('M') }}</small>
                                @else
                                    <strong>—</strong>
                                    <small>prazo</small>
                                @endif
                            </span>
                            <div class="dash-feed__body">
                                <div class="dash-feed__row">
                                    <p class="dash-feed__name">{{ $task->title }}</p>
                                    <span class="dash-tag dash-tag--prio dash-tag--prio-{{ $task->priority->value }}">{{ $task->priority->label() }}</span>
                                </div>
                                <p class="dash-feed__subject">{{ $task->project?->name ?? 'Sem projeto' }}</p>
                                <div class="dash-feed__tags">
                                    <span class="dash-tag dash-tag--urgency dash-tag--urgency-{{ $urgency }}">{{ $task->dueLabel() }}</span>
                                    <span class="dash-tag">{{ $task->status->label() }}</span>
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="dash-feed__empty">
                        <p>Nenhuma tarefa aberta.</p>
                        <p class="dash-feed__empty-hint">Crie compromissos no calendário — lembretes Telegram avisam ~10 min antes.</p>
                    </li>
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
