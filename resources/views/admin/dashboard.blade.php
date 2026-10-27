@extends('layouts.admin')

@section('content')
    @php
        $adminFirstName = explode(' ', trim(auth()->user()->name))[0] ?? 'olá';
    @endphp
    <div class="admin-hero" data-admin-dashboard>
        <div>
            <p class="admin-hero__kicker">Início</p>
            <h1 class="admin-hero__title">Bem-vindo de volta, {{ $adminFirstName }}!</h1>
            <p class="admin-hero__lead">Visão rápida da operação BURI-TI</p>
        </div>
        <div class="admin-hero__date" title="{{ now()->isoFormat('dddd, D [de] MMMM [de] YYYY') }}">
            <strong>{{ now()->translatedFormat('d M Y') }}</strong>
            <span>Hoje</span>
        </div>
    </div>

    <x-admin.crm-journey class="mb-5" />

    <form method="GET" action="{{ route('admin.dashboard') }}" class="mb-5 flex flex-wrap items-end gap-3">
        <label class="text-sm text-mist">
            Filtrar por empresa
            <select name="company_id" class="mt-1 block rounded-sm border border-line bg-ink/40 px-2 py-1.5 text-snow" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) $companyId === (string) $company->id)>{{ $company->displayName() }}</option>
                @endforeach
            </select>
        </label>
        @if($companyId)
            <a href="{{ route('admin.dashboard') }}" class="text-sm text-brand-bright hover:underline">Limpar</a>
        @endif
    </form>

    <section class="dash-panel dash-panel--actions mb-6">
        <header class="dash-panel__head">
            <div class="dash-panel__title-wrap">
                <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="opportunity" class="h-5 w-5" /></span>
                <div>
                    <p class="dash-panel__eyebrow">Condução</p>
                    <h2 class="dash-panel__title">Próximas ações</h2>
                </div>
            </div>
        </header>
        <div class="dash-actions">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Mensagens</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse($inbox['unlinked_messages'] as $message)
                        <li><a href="{{ route('admin.messages.show', $message) }}" class="text-brand-bright hover:underline">{{ $message->name }} — {{ $message->subject }}</a></li>
                    @empty
                        <li class="text-mist">Caixa de entrada comercial em dia.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Tarefas em atraso</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse($inbox['overdue_tasks'] as $task)
                        <li><a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="text-brand-bright hover:underline" data-task-open="{{ $task->id }}">{{ $task->title }}</a></li>
                    @empty
                        <li class="text-mist">Sem atrasos.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Oportunidades paradas</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse($inbox['stale_opportunities'] as $opportunity)
                        <li><a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="text-brand-bright hover:underline">{{ $opportunity->title }}</a></li>
                    @empty
                        <li class="text-mist">Funil com atividade recente.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Meet sem link</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse($inbox['meet_missing'] as $task)
                        <li><a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="text-brand-bright hover:underline" data-task-open="{{ $task->id }}">{{ $task->title }}</a></li>
                    @empty
                        <li class="text-mist">Nada pendente.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Agenda sem sync Google</p>
                <ul class="mt-2 space-y-1 text-sm">
                    @forelse($inbox['google_unsynced'] as $task)
                        <li><a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="text-brand-bright hover:underline" data-task-open="{{ $task->id }}">{{ $task->title }}</a></li>
                    @empty
                        <li class="text-mist">Nada pendente.</li>
                    @endforelse
                </ul>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-mist">Funil</p>
                <p class="mt-2 text-2xl font-semibold text-snow">{{ $funnel['win_rate'] }}%</p>
                <p class="text-xs text-mist">Taxa de ganho ({{ $funnel['won'] }}/{{ $funnel['closed'] }} fechados)</p>
            </div>
        </div>
    </section>

    <x-admin.crm-funnel
        :counts="$opportunityStageCounts"
        :filter-base="route('admin.opportunities.index', ['view' => 'board'])"
        class="mb-6"
    />

    <div class="dash-stats">
        @foreach ($kpiCards as $card)
            <a
                href="{{ $card['href'] }}"
                class="dash-stat dash-stat--{{ $card['tone'] }} is-{{ $card['direction'] }}"
                data-kpi-polarity="{{ $card['polarity'] }}"
                data-kpi-dir="{{ $card['direction'] }}"
                aria-label="{{ $card['aria'] }}"
                title="{{ $card['aria'] }}"
            >
                <span class="dash-stat__icon" aria-hidden="true">
                    <x-ui.icon :name="$card['icon']" class="h-4 w-4" />
                </span>
                <span class="dash-stat__label">{{ $card['label'] }}</span>
                <span class="dash-stat__value-row">
                    <span class="dash-stat__value">{{ $card['value'] }}</span>
                    <span class="dash-stat__delta">{{ $card['delta_label'] }}</span>
                </span>
                <svg class="dash-stat__spark" viewBox="0 0 72 28" aria-hidden="true">
                    <path class="dash-stat__spark-area" d="{{ $card['area'] }}" />
                    <path class="dash-stat__spark-line" d="{{ $card['path'] }}" />
                </svg>
            </a>
        @endforeach
    </div>

    <div class="dash-board-toolbar mt-8">
        <p>Arraste o cabeçalho para mover. O canto inferior direito muda a largura. Cartões baixos empilham na mesma coluna. Espaço livre e Google Agenda começam a meio, na última linha.</p>
        <button type="button" class="dash-board-toolbar__reset" data-dash-reset>Repor disposição</button>
    </div>
    <div class="dash-panels dash-board" data-dash-board data-dash-storage="buriti-dash-layout-v2">
        <section class="dash-panel dash-panel--messages dash-widget" data-dash-widget="messages" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Mensagens'])
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

        <section class="dash-panel dash-panel--contacts dash-widget" data-dash-widget="contacts" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Contatos'])
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

        <section class="dash-panel dash-panel--tasks dash-widget" data-dash-widget="tasks" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Tarefas'])
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
                        <a href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}" class="dash-feed__item dash-feed__item--task" data-urgency="{{ $urgency }}" data-task-open="{{ $task->id }}">
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

        <section class="dash-panel dash-panel--conduct dash-widget" data-dash-widget="conduct" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Atividades'])
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="journey" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Condução</p>
                        <h2 class="dash-panel__title">Atividades dos contatos</h2>
                        <p class="dash-panel__lead">Chamadas, reuniões, e-mails e notas registradas nas fichas.</p>
                    </div>
                </div>
                <div class="dash-panel__meta">
                    <span class="dash-panel__pill">{{ $recentActivities->count() }} recente{{ $recentActivities->count() === 1 ? '' : 's' }}</span>
                    <button type="button" class="dash-panel__action" data-bulk-activity-open>
                        <x-ui.icon name="task" class="h-3.5 w-3.5" />
                        Registrar atividade
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
                            ? route('admin.contacts.activities.edit', [$contact, $activity])
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
                        <p>Nenhuma atividade registrada ainda.</p>
                        <p class="dash-feed__empty-hint">Registe a primeira chamada, reunião ou nota para começar a condução.</p>
                        <button type="button" class="dash-panel__action dash-panel__action--primary mt-3" data-bulk-activity-open>
                            <x-ui.icon name="task" class="h-3.5 w-3.5" />
                            Registrar atividade
                        </button>
                    </li>
                @endforelse
            </ol>
        </section>
    </div>

    <div class="dash-panels dash-board mt-8" data-dash-board data-dash-storage="buriti-dash-end-v1">
        <section class="dash-panel dash-widget dash-widget--end" id="ideias" data-dash-widget="ideas" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Espaço livre'])
            <header class="dash-panel__head">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-bright">Espaço livre</p>
                    <h2 class="font-display text-xl font-semibold sm:text-2xl">Ideias e rascunhos</h2>
                    <p class="mt-1 text-sm text-mist">Post-its arrastáveis. Aloque a uma empresa e, se quiser, a um contato dela.</p>
                </div>
                <form method="POST" action="{{ route('admin.idea-notes.store') }}">
                    @csrf
                    <input type="hidden" name="color" value="amber">
                    <button type="submit" class="admin-btn admin-btn--primary">
                        + Novo post-it
                    </button>
                </form>
            </header>

            <div
                class="postit-board idea-board grid gap-5 sm:grid-cols-2"
                data-idea-board
                data-reorder-url="{{ $ideaReorderUrl }}"
            >
                @forelse($ideaNotes as $index => $note)
                    @include('admin.dashboard.partials.idea-note', [
                        'note' => $note,
                        'index' => $index,
                        'ideaColors' => $ideaColors,
                        'ideaCompanies' => $ideaCompanies,
                        'ideaContacts' => $ideaContacts,
                    ])
                @empty
                    <div class="col-span-full rounded-sm border border-dashed border-line px-6 py-12 text-center text-sm text-mist" data-idea-empty>
                        <p>Nenhuma ideia ainda. Crie um post-it e use como quiser.</p>
                    </div>
                @endforelse
            </div>
            <p class="mt-3 text-xs text-mist" data-idea-board-status>Arraste pelo ícone ⋮⋮ para reordenar. As alterações de ordem são salvas ao soltar.</p>
        </section>

        <section class="dash-panel dash-widget dash-widget--end" data-dash-widget="calendar" data-span="1" data-span-default="1">
            @include('admin.dashboard.partials.widget-tools', ['label' => 'Google Agenda'])
            @if($googleCalendarSrc)
                <x-admin.calendar-embed :src="$googleCalendarSrc" title="Google Agenda" fill />
            @else
                <div class="flex h-full min-h-[22rem] flex-1 flex-col justify-center rounded-sm border border-dashed border-line px-4 py-4 pl-12 text-sm text-mist">
                    <p class="font-medium text-snow">Google Agenda</p>
                    <p class="mt-1">Ligue o embed da Agenda nas configurações para ver o calendário aqui, ao lado do espaço livre.</p>
                    <a href="{{ route('admin.settings.edit') }}#google-integration" class="mt-2 inline-flex text-sm font-semibold text-brand-bright hover:underline">Configurar integração →</a>
                </div>
            @endif
        </section>
    </div>

    @include('admin.contacts.partials.bulk-activity-dialog', [
        'pickerContacts' => $pickerContacts,
        'activityTypes' => $activityTypes,
        'openTasks' => $openTasks,
    ])
@endsection
