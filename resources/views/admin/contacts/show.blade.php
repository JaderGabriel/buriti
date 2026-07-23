@extends('layouts.admin')

@section('content')
@php
    $phoneLabel = \App\Support\PhoneNumber::format($contact->phone);
    $tel = $contact->telUrl();
    $wa = $contact->whatsappUrl();
    $initials = collect(preg_split('/\s+/', trim($contact->name)) ?: [])
        ->filter()
        ->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
        ->implode('');
    $statusTone = $contact->status->tone();
    $openTaskCount = $contact->tasks->filter(fn ($t) => $t->status !== \App\Enums\TaskStatus::Done)->count();
    $activeProjects = $contact->projects->filter(fn ($p) => $p->status === \App\Enums\ProjectStatus::Active)->count();
@endphp

<div class="contact-dossier">
    <a href="{{ route('admin.contacts.index') }}" class="contact-dossier__back">
        <x-ui.icon name="journey" class="h-4 w-4" />
        Contatos
    </a>

    <header class="contact-dossier__hero contact-dossier__hero--{{ $statusTone }}">
        <div class="contact-dossier__identity">
            <span class="contact-dossier__avatar" aria-hidden="true">{{ $initials ?: '?' }}</span>
            <div class="min-w-0">
                <p class="contact-dossier__eyebrow">Ficha comercial</p>
                <h1 class="contact-dossier__title">{{ $contact->name }}</h1>
                <div class="contact-dossier__tags">
                    <x-admin.crm-badge :status="$contact->status" />
                    <span class="contact-dossier__chip">
                        <x-ui.icon name="flow" class="h-3.5 w-3.5" />
                        {{ $contact->source->label() }}
                    </span>
                    @if($contact->clientCompany)
                        <a href="{{ route('admin.companies.show', $contact->clientCompany) }}" class="contact-dossier__chip contact-dossier__chip--link">
                            <x-ui.icon name="company" class="h-3.5 w-3.5" />
                            {{ $contact->clientCompany->displayName() }}
                        </a>
                    @elseif($contact->companyLabel())
                        <span class="contact-dossier__chip">
                            <x-ui.icon name="company" class="h-3.5 w-3.5" />
                            {{ $contact->companyLabel() }}
                        </span>
                    @endif
                    @if($contact->role)
                        <span class="contact-dossier__chip">{{ $contact->role }}</span>
                    @endif
                </div>
            </div>
        </div>

        <div class="contact-dossier__quick">
            @if($tel)
                <a href="{{ $tel }}" class="contact-dossier__quick-btn contact-dossier__quick-btn--call" title="Ligar">
                    <x-ui.icon name="phone" class="h-4 w-4" />
                    Ligar
                </a>
            @endif
            @if($wa)
                <a href="{{ $wa }}" target="_blank" rel="noopener" class="contact-dossier__quick-btn contact-dossier__quick-btn--wa" title="WhatsApp">
                    <x-ui.icon name="whatsapp" class="h-4 w-4" />
                    WhatsApp
                </a>
            @endif
            @if($contact->email)
                <a href="mailto:{{ $contact->email }}" class="contact-dossier__quick-btn contact-dossier__quick-btn--mail" title="E-mail">
                    <x-ui.icon name="mail" class="h-4 w-4" />
                    E-mail
                </a>
            @endif
            <button
                type="button"
                class="contact-dossier__quick-btn contact-dossier__quick-btn--activity"
                data-bulk-activity-open
                data-bulk-activity-ids="{{ $contact->id }}"
            >
                <x-ui.icon name="task" class="h-4 w-4" />
                Atividade
            </button>
            <a href="{{ route('admin.contacts.edit', $contact) }}" class="contact-dossier__quick-btn">
                <x-ui.icon name="settings" class="h-4 w-4" />
                Editar
            </a>
            <a href="{{ route('admin.opportunities.create', ['contact_id' => $contact->id]) }}" class="contact-dossier__quick-btn contact-dossier__quick-btn--primary">
                <x-ui.icon name="opportunity" class="h-4 w-4" />
                Oportunidade
            </a>
            <form method="POST" action="{{ route('admin.contacts.destroy', $contact) }}" data-confirm="Remover este contato e dados relacionados?">
                @csrf
                @method('DELETE')
                <button class="contact-dossier__quick-btn contact-dossier__quick-btn--danger" type="submit">
                    <x-ui.icon name="trash" class="h-4 w-4" />
                    Remover
                </button>
            </form>
        </div>
    </header>

    <x-admin.crm-journey current="contact" class="mb-5" />

    <div class="contact-dossier__stats">
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--project"><x-ui.icon name="project" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $contact->projects->count() }}</strong>
                <small>projetos · {{ $activeProjects }} ativos</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--activity"><x-ui.icon name="journey" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $contact->activities->count() }}</strong>
                <small>atividades no histórico</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--opp"><x-ui.icon name="opportunity" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $contact->opportunities->count() }}</strong>
                <small>oportunidades</small>
            </div>
        </div>
        <div class="contact-dossier__stat">
            <span class="contact-dossier__stat-icon contact-dossier__stat-icon--task"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
            <div>
                <strong>{{ $openTaskCount }}</strong>
                <small>tarefas em aberto</small>
            </div>
        </div>
    </div>

    <div class="contact-dossier__layout">
        <div class="contact-dossier__main">
            {{-- Condução: projetos + atividades --}}
            <section class="contact-conduct" id="conducao">
                <header class="contact-conduct__header">
                    <div>
                        <p class="contact-conduct__eyebrow">Condução</p>
                        <h2 class="contact-conduct__title">Projetos e atividades</h2>
                        <p class="contact-conduct__lead">Onde o relacionamento está a andar — entregas vinculadas e o fio do histórico comercial.</p>
                    </div>
                    <button
                        type="button"
                        class="pm-btn pm-btn--primary"
                        data-bulk-activity-open
                        data-bulk-activity-ids="{{ $contact->id }}"
                    >
                        <x-ui.icon name="task" class="h-4 w-4" />
                        Nova atividade
                    </button>
                </header>

                <div class="contact-conduct__grid">
                    <article class="contact-conduct__panel contact-conduct__panel--projects">
                        <div class="contact-conduct__panel-head">
                            <span class="contact-conduct__badge contact-conduct__badge--project">
                                <x-ui.icon name="project" class="h-4 w-4" />
                            </span>
                            <div>
                                <h3>Projetos / produtos</h3>
                                <p>Entregas e pacotes ligados a este contato</p>
                            </div>
                        </div>

                        <ul class="contact-conduct__project-list">
                            @forelse($contact->projects as $project)
                                <li class="contact-conduct__project contact-conduct__project--{{ $project->status->tone() }}">
                                    <div class="min-w-0">
                                        <p class="contact-conduct__project-name">{{ $project->name }}</p>
                                        <p class="contact-conduct__project-meta">
                                            <span class="contact-conduct__status-pill contact-conduct__status-pill--{{ $project->status->tone() }}">
                                                {{ $project->status->label() }}
                                            </span>
                                            @if($project->clientCompany)
                                                · {{ $project->clientCompany->displayName() }}
                                            @endif
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('admin.contacts.projects.detach', [$contact, $project]) }}" data-confirm="Desvincular projeto?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="contact-conduct__unlink" type="submit" title="Desvincular">✕</button>
                                    </form>
                                </li>
                            @empty
                                <li class="contact-conduct__empty">
                                    <x-ui.icon name="project" class="h-8 w-8 opacity-40" />
                                    <p>Nenhum projeto vinculado ainda.</p>
                                </li>
                            @endforelse
                        </ul>

                        <form method="POST" action="{{ route('admin.contacts.projects.attach', $contact) }}" class="contact-conduct__attach">
                            @csrf
                            <select name="project_id" required>
                                <option value="">Vincular projeto…</option>
                                @foreach($allProjects as $project)
                                    <option value="{{ $project->id }}">{{ $project->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit">Vincular</button>
                        </form>
                    </article>

                    <article class="contact-conduct__panel contact-conduct__panel--timeline">
                        <div class="contact-conduct__panel-head">
                            <span class="contact-conduct__badge contact-conduct__badge--activity">
                                <x-ui.icon name="journey" class="h-4 w-4" />
                            </span>
                            <div>
                                <h3>Linha de atividades</h3>
                                <p>Chamadas, reuniões, e-mails e notas</p>
                            </div>
                        </div>

                        <ol class="contact-timeline">
                            @forelse($contact->activities as $activity)
                                @php
                                    $meta = $activityTypeMeta[$activity->type->value] ?? ['icon' => 'task', 'tone' => 'brand', 'label' => $activity->type->label()];
                                @endphp
                                <li class="contact-timeline__item contact-timeline__item--{{ $meta['tone'] }}" id="activity-{{ $activity->id }}">
                                    <span class="contact-timeline__icon" aria-hidden="true">
                                        <x-ui.icon :name="$meta['icon']" class="h-4 w-4" />
                                    </span>
                                    <div class="contact-timeline__body">
                                        <div class="contact-timeline__top">
                                            <span class="contact-timeline__type">{{ $meta['label'] }}</span>
                                            <time>{{ optional($activity->happened_at)->format('d/m/Y H:i') ?? $activity->created_at->format('d/m/Y H:i') }}</time>
                                            <a
                                                href="{{ route('admin.contacts.activities.edit', [$contact, $activity]) }}"
                                                class="contact-timeline__edit"
                                            >Editar</a>
                                            <form method="POST" action="{{ route('admin.contacts.activities.destroy', [$contact, $activity]) }}" data-confirm="Remover atividade?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="contact-timeline__remove">Remover</button>
                                            </form>
                                        </div>
                                        <a href="{{ route('admin.contacts.activities.edit', [$contact, $activity]) }}" class="contact-timeline__link">
                                            <p class="contact-timeline__subject">{{ $activity->subject ?: 'Sem assunto' }}</p>
                                            @if($activity->body)
                                                <p class="contact-timeline__detail">{{ $activity->body }}</p>
                                            @endif
                                            <p class="contact-timeline__meta">
                                                @if($activity->user) {{ $activity->user->name }} @endif
                                                @if($activity->opportunity) · {{ $activity->opportunity->title }} @endif
                                                @if($activity->task) · tarefa: {{ $activity->task->title }} @endif
                                                <span class="contact-timeline__hint"> · clicar para editar</span>
                                            </p>
                                        </a>
                                    </div>
                                </li>
                            @empty
                                <li class="contact-conduct__empty">
                                    <x-ui.icon name="journey" class="h-8 w-8 opacity-40" />
                                    <p>Sem atividades — registe a primeira para começar a condução.</p>
                                </li>
                            @endforelse
                        </ol>
                    </article>
                </div>
            </section>

            <div class="contact-dossier__secondary">
                <article class="contact-panel">
                    <header class="contact-panel__head">
                        <span class="contact-panel__icon contact-panel__icon--sky"><x-ui.icon name="contact" class="h-4 w-4" /></span>
                        <h2>Dados</h2>
                    </header>
                    <dl class="contact-panel__facts">
                        <div>
                            <dt><x-ui.icon name="mail" class="h-3.5 w-3.5" /> E-mail</dt>
                            <dd>
                                @if($contact->email)
                                    <a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>
                                @else — @endif
                            </dd>
                        </div>
                        <div>
                            <dt><x-ui.icon name="phone" class="h-3.5 w-3.5" /> Telefone</dt>
                            <dd>{{ $phoneLabel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt><x-ui.icon name="company" class="h-3.5 w-3.5" /> Empresa</dt>
                            <dd>
                                @if($contact->clientCompany)
                                    <a href="{{ route('admin.companies.show', $contact->clientCompany) }}">{{ $contact->clientCompany->displayName() }}</a>
                                @else
                                    {{ $contact->companyLabel() ?? '—' }}
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt><x-ui.icon name="users" class="h-3.5 w-3.5" /> Cargo</dt>
                            <dd>{{ $contact->role ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt><x-ui.icon name="whatsapp" class="h-3.5 w-3.5" /> Canal preferido</dt>
                            <dd class="uppercase tracking-wide">{{ $contact->preferred_channel ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($contact->notes)
                        <p class="contact-panel__notes">{{ $contact->notes }}</p>
                    @endif
                </article>

                <article class="contact-panel">
                    <header class="contact-panel__head">
                        <span class="contact-panel__icon contact-panel__icon--emerald"><x-ui.icon name="opportunity" class="h-4 w-4" /></span>
                        <h2>Oportunidades</h2>
                    </header>
                    <ul class="contact-panel__list">
                        @forelse($contact->opportunities as $opportunity)
                            <li>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="font-medium text-snow">{{ $opportunity->title }}</p>
                                    <x-admin.crm-badge :stage="$opportunity->stage" compact />
                                </div>
                                <p class="text-xs text-mist">
                                    {{ $opportunity->project?->name ?? 'Sem projeto' }}
                                    @if($opportunity->value) · R$ {{ number_format((float) $opportunity->value, 2, ',', '.') }} @endif
                                </p>
                                <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="mt-1 inline-block text-xs text-brand-bright hover:underline">Editar</a>
                            </li>
                        @empty
                            <li class="text-sm text-mist">Nenhuma oportunidade.</li>
                        @endforelse
                    </ul>
                </article>

                <article class="contact-panel">
                    <header class="contact-panel__head">
                        <span class="contact-panel__icon contact-panel__icon--amber"><x-ui.icon name="message" class="h-4 w-4" /></span>
                        <h2>Mensagens do site</h2>
                    </header>
                    <ul class="contact-panel__list">
                        @forelse($contact->messages as $message)
                            <li>
                                <a href="{{ route('admin.messages.show', $message) }}" class="text-sm text-brand-bright hover:underline">
                                    {{ $message->subject }}
                                </a>
                                <span class="text-xs text-mist"> · {{ $message->created_at->format('d/m/Y') }}</span>
                            </li>
                        @empty
                            <li class="text-sm text-mist">Nenhuma mensagem ligada.</li>
                        @endforelse
                    </ul>
                </article>

                <article class="contact-panel">
                    <header class="contact-panel__head">
                        <span class="contact-panel__icon contact-panel__icon--orange"><x-ui.icon name="calendar" class="h-4 w-4" /></span>
                        <h2>Tarefas / agenda</h2>
                    </header>
                    <ul class="contact-panel__list">
                        @forelse($contact->tasks as $task)
                            <li class="text-sm">
                                <span class="font-medium text-snow">{{ $task->title }}</span>
                                <span class="text-xs text-mist">
                                    · {{ $task->status->label() }}
                                    @if($task->due_at) · {{ $task->due_at->format('d/m/Y H:i') }} @endif
                                </span>
                            </li>
                        @empty
                            <li class="text-sm text-mist">Nenhuma tarefa ligada.</li>
                        @endforelse
                    </ul>
                </article>

                <div class="contact-dossier__secondary-span">
                    <x-admin.attachments-panel
                        :attachable="$contact"
                        type="contacts"
                        :kinds="['document']"
                        layout="folder"
                        title="Pasta de arquivos"
                        description="Contratos, propostas e PDFs deste contato. Itens ocultos ficam na lixeira."
                    />
                </div>
            </div>
        </div>

        <aside class="contact-dossier__aside">
            <article class="contact-activity-form">
                <header class="contact-activity-form__head">
                    <span class="contact-activity-form__icon"><x-ui.icon name="task" class="h-5 w-5" /></span>
                    <div>
                        <h2>Registar atividade</h2>
                        <p>Fica neste contato. Para vários, use o botão Atividade.</p>
                    </div>
                </header>
                <form method="POST" action="{{ route('admin.contacts.activities.store', $contact) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="block text-sm">
                        <span class="text-mist">Tipo</span>
                        <select name="type" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            @foreach($activityTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input name="subject" label="Assunto" />
                    <label class="block text-sm">
                        <span class="text-mist">Detalhe</span>
                        <textarea name="body" rows="3" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow"></textarea>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Oportunidade (opcional)</span>
                        <select name="opportunity_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            <option value="">—</option>
                            @foreach($contact->opportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Tarefa / agenda (opcional)</span>
                        <select name="task_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            <option value="">—</option>
                            @foreach($openTasks as $task)
                                <option value="{{ $task->id }}">{{ $task->title }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs text-mist">Ao vincular uma tarefa, ela fica marcada como concluída.</span>
                    </label>
                    <x-ui.input type="datetime-local" name="happened_at" label="Quando" :value="old('happened_at', now()->format('Y-m-d\\TH:i'))" />
                    <x-ui.button type="submit">Guardar atividade</x-ui.button>
                </form>
            </article>
        </aside>
    </div>
</div>

@include('admin.contacts.partials.bulk-activity-dialog', [
    'pickerContacts' => $pickerContacts,
    'activityTypes' => $activityTypes,
    'openTasks' => $openTasks,
    'preselectedContactIds' => [$contact->id],
])
@endsection
