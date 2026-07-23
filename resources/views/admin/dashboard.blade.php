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

    <div class="dash-stats">
        @foreach ([
            ['Mensagens novas', $unreadMessages, route('admin.messages.index'), 'messages'],
            ['Total mensagens', $totalMessages, route('admin.messages.index'), null],
            ['Contatos CRM', $contactsCount, route('admin.contacts.index'), 'contacts'],
            ['Oportunidades abertas', $openOpportunities, route('admin.opportunities.index'), 'opps'],
            ['Projetos', $projectsCount, route('admin.projects.index'), 'projects'],
            ['Tarefas abertas', $openTasksCount, route('admin.tasks.index'), 'tasks'],
        ] as [$label, $value, $href, $tone])
            <a
                href="{{ $href }}"
                @class(['dash-stat', $tone ? 'dash-stat--'.$tone : null])
            >
                <span class="dash-stat__value">{{ $value }}</span>
                <span class="dash-stat__label">{{ $label }}</span>
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
                        <span class="dash-panel__pill">{{ $openTasksCount }} aberta{{ $openTasksCount === 1 ? '' : 's' }}</span>
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
                                @php $latestActivity = $task->activities->first(); @endphp
                                @if($latestActivity)
                                    <p class="dash-feed__preview">
                                        {{ $latestActivity->type->label() }} ·
                                        {{ \Illuminate\Support\Str::limit(strip_tags((string) ($latestActivity->body ?: $latestActivity->subject)), 70) }}
                                    </p>
                                @endif
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

        <section class="dash-panel dash-panel--conduct">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="journey" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Condução</p>
                        <h2 class="dash-panel__title">Atividades dos contatos</h2>
                        <p class="dash-panel__lead">Chamadas, reuniões, e-mails e notas registadas nas fichas.</p>
                    </div>
                </div>
                <div class="dash-panel__meta">
                    <span class="dash-panel__pill">{{ $recentActivities->count() }} recente{{ $recentActivities->count() === 1 ? '' : 's' }}</span>
                    <button type="button" class="dash-panel__action" data-bulk-activity-open>
                        <x-ui.icon name="task" class="h-3.5 w-3.5" />
                        Registar atividade
                    </button>
                    <a href="{{ route('admin.contacts.index') }}" class="dash-panel__link">Contatos</a>
                </div>
            </header>

            <ol class="dash-conduct">
                @forelse($recentActivities as $activity)
                    @php
                        $contact = $activity->contact;
                        $tone = $activity->type->tone();
                        $initials = $contact
                            ? collect(preg_split('/\s+/', trim($contact->name)) ?: [])
                                ->filter()
                                ->take(2)
                                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                ->implode('')
                            : '?';
                        $when = $activity->happened_at ?? $activity->created_at;
                        $subject = filled($activity->subject) ? $activity->subject : $activity->type->label();
                        $body = trim((string) ($activity->body ?? ''));
                        $href = $contact
                            ? route('admin.contacts.show', $contact).'#conducao'
                            : route('admin.contacts.index');
                    @endphp
                    <li class="dash-conduct__item dash-conduct__item--{{ $tone }}">
                        <a href="{{ $href }}" class="dash-conduct__card">
                            <span class="dash-conduct__rail" aria-hidden="true"></span>
                            <span class="dash-conduct__type" title="{{ $activity->type->label() }}">
                                <x-ui.icon :name="$activity->type->icon()" class="h-4 w-4" />
                            </span>
                            <div class="dash-conduct__main">
                                <div class="dash-conduct__top">
                                    <span class="dash-conduct__badge">{{ $activity->type->label() }}</span>
                                    <time class="dash-conduct__when" datetime="{{ $when?->toIso8601String() }}">
                                        {{ $when?->format('d/m H:i') ?? '—' }}
                                        <span>· {{ $when?->diffForHumans() }}</span>
                                    </time>
                                </div>
                                <p class="dash-conduct__subject">{{ $subject }}</p>
                                @if($body !== '')
                                    <p class="dash-conduct__body">{{ \Illuminate\Support\Str::limit($body, 180) }}</p>
                                @endif
                                <div class="dash-conduct__foot">
                                    @if($contact)
                                        <span class="dash-conduct__contact">
                                            <span class="dash-conduct__avatar" data-tone="{{ $contact->status->tone() }}">{{ $initials ?: '?' }}</span>
                                            <span>
                                                <strong>{{ $contact->name }}</strong>
                                                @if($contact->companyLabel())
                                                    <small>{{ $contact->companyLabel() }}</small>
                                                @endif
                                            </span>
                                        </span>
                                    @endif
                                    <span class="dash-conduct__meta">
                                        @if($activity->user)
                                            {{ $activity->user->name }}
                                        @endif
                                        @if($activity->opportunity)
                                            · {{ $activity->opportunity->title }}
                                        @endif
                                        @if($activity->task)
                                            · agenda: {{ $activity->task->title }}
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="dash-feed__empty">
                        <p>Nenhuma atividade registada ainda.</p>
                        <p class="dash-feed__empty-hint">Registe a primeira chamada, reunião ou nota para começar a condução.</p>
                        <button type="button" class="dash-panel__action dash-panel__action--primary mt-3" data-bulk-activity-open>
                            <x-ui.icon name="task" class="h-3.5 w-3.5" />
                            Registar atividade
                        </button>
                    </li>
                @endforelse
            </ol>
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

    @include('admin.contacts.partials.bulk-activity-dialog', [
        'pickerContacts' => $pickerContacts,
        'activityTypes' => $activityTypes,
        'openTasks' => $openTasks,
    ])
@endsection
