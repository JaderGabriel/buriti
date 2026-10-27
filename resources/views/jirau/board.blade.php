@extends('layouts.jirau')

@section('content')
<div
    class="jirau-workspace"
    data-jirau-board
    data-move-url="{{ $moveUrlTemplate }}"
    data-can-drag="{{ $isAdmin ? '1' : '0' }}"
>
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Jirau · {{ $jirau->project?->name }}</p>
            <h1 class="pm-workspace__title">
                {{ $jirau->name }}
                @unless($jirau->is_active)
                    <span class="jirau-chip jirau-chip--warn">Inativo</span>
                @endunless
            </h1>
            <p class="pm-workspace__lead">
                {{ $jirau->template?->lead() ?? 'Demandas entram na triagem; o admin estima e posiciona.' }}
                Modelo: {{ $jirau->template?->label() }}.
            </p>
            @if($isAdmin && $jirau->project && $jirau->project->jiraus->count() > 1)
                <nav class="jirau-board-switch" aria-label="Quadros deste projeto">
                    @foreach($jirau->project->jiraus as $board)
                        <a href="{{ route('jirau.show', $board) }}" @class(['is-current' => (int) $board->id === (int) $jirau->id])>
                            {{ $board->name }}
                            <small>{{ $board->template?->label() }}</small>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
        <div class="jirau-workspace__actions">
            @if($isAdmin && $jirau->project)
                <a href="{{ route('admin.projects.edit', $jirau->project) }}" class="pm-btn pm-btn--ghost">Ficha do projeto</a>
            @endif
            @if($isAdmin)
                <a href="{{ route('jirau.analytics.show', $jirau) }}" class="pm-btn pm-btn--ghost">Analítica</a>
                <form method="POST" action="{{ route('jirau.status', $jirau) }}" class="inline">
                    @csrf
                    <input type="hidden" name="is_active" value="{{ $jirau->is_active ? 0 : 1 }}">
                    <button type="submit" class="pm-btn pm-btn--ghost">
                        {{ $jirau->is_active ? 'Desativar quadro' : 'Ativar quadro' }}
                    </button>
                </form>
            @endif
            <button type="button" class="pm-btn pm-btn--ghost" data-jirau-toggle="filters">Filtros</button>
            @if($isAdmin)
                <button type="button" class="pm-btn pm-btn--ghost" data-jirau-toggle="steps">Etapas</button>
                <button type="button" class="pm-btn pm-btn--ghost" data-jirau-toggle="members">Pessoas</button>
                <button type="button" class="pm-btn pm-btn--ghost" data-jirau-toggle="new-board">Novo quadro</button>
                <button type="button" class="pm-btn pm-btn--primary" data-jirau-toggle="new-card">Novo cartão</button>
            @else
                <button type="button" class="pm-btn pm-btn--primary" data-jirau-toggle="demand">Nova demanda</button>
            @endif
        </div>
    </div>

    <form method="GET" action="{{ route('jirau.show', $jirau) }}" class="jirau-filters" data-jirau-panel="filters" hidden>
        <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Buscar título ou descrição" class="jirau-input">
        <select name="type" class="jirau-input">
            <option value="">Tipo</option>
            @foreach($types as $value => $label)
                <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="priority" class="jirau-input">
            <option value="">Prioridade</option>
            @foreach($priorities as $value => $label)
                <option value="{{ $value }}" @selected($filters['priority'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="label" class="jirau-input">
            <option value="">Etiqueta</option>
            @foreach($jirau->labels as $label)
                <option value="{{ $label->id }}" @selected((string) $filters['label'] === (string) $label->id)>{{ $label->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="pm-btn pm-btn--primary">Filtrar</button>
        <a href="{{ route('jirau.show', $jirau) }}" class="pm-btn pm-btn--ghost">Limpar</a>
    </form>

    @if(! $isAdmin)
        <form method="POST" action="{{ route('jirau.demands.store', $jirau) }}" class="jirau-compose" data-jirau-panel="demand" @if(! $errors->has('title')) hidden @endif>
            @csrf
            <h2>Nova demanda</h2>
            <p>O cartão entra em {{ $jirau->inboxColumn()?->name ?? 'entrada' }}. Você não define faixa nem estimativa — o admin do quadro posiciona o trabalho.</p>
            <x-ui.input name="title" label="O que precisa ser feito?" required />
            <x-ui.input type="textarea" name="description" label="Contexto" rows="4" />
            <label class="block text-sm">
                <span class="text-mist">Prioridade percebida</span>
                <select name="priority" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                    @foreach($priorities as $value => $label)
                        <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="pm-btn pm-btn--primary">Enviar demanda</button>
        </form>
    @endif

    @if($isAdmin)
        <form method="POST" action="{{ route('jirau.cards.store', $jirau) }}" class="jirau-compose" data-jirau-panel="new-card" hidden>
            @csrf
            <h2>Novo cartão</h2>
            <div class="jirau-compose__grid">
                <x-ui.input name="title" label="Título" required />
                <label class="block text-sm">
                    <span class="text-mist">Tipo</span>
                    <select name="type" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                        @foreach($adminTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Faixa</span>
                    <select name="column_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                        @php
                            $defaultColumn = $jirau->columns->first(fn ($column) => ! $column->is_inbox && ! $column->is_done) ?? $jirau->columns->first();
                        @endphp
                        @foreach($jirau->columns as $column)
                            <option value="{{ $column->id }}" @selected((int) $column->id === (int) $defaultColumn?->id)>{{ $column->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Prioridade</span>
                    <select name="priority" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                        @foreach($priorities as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Estimativa (pontos)</span>
                    <select name="estimate" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                        <option value="">Sem estimativa</option>
                        @foreach($estimates as $pts)
                            <option value="{{ $pts }}">{{ $pts }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.input type="datetime-local" name="due_at" label="Prazo" />
                <label class="block text-sm">
                    <span class="text-mist">Responsável (equipe TI)</span>
                    <select name="assigned_to" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                        <option value="">Não alocado</option>
                        @foreach($assignees as $person)
                            <option value="{{ $person->id }}">{{ $person->name }}{{ $person->jirau_role ? ' · '.$person->jirau_role->label() : ' · Admin' }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <x-ui.input type="textarea" name="description" label="Descrição" rows="3" />
            <fieldset class="jirau-labels-pick">
                <legend>Etiquetas</legend>
                @foreach($jirau->labels as $label)
                    <label>
                        <input type="checkbox" name="label_ids[]" value="{{ $label->id }}">
                        <span class="jirau-label" style="--jirau-label: {{ $label->color }}">{{ $label->name }}</span>
                    </label>
                @endforeach
            </fieldset>
            <button type="submit" class="pm-btn pm-btn--primary">Criar cartão</button>
        </form>

        <section class="jirau-members" data-jirau-panel="steps" hidden>
            <h2>Etapas do projeto</h2>
            <p>O % da ficha do projeto segue as etapas. Junte-as a este Jirau ou crie novas daqui — vice-versa na ficha.</p>
            <ul class="jirau-members__list">
                @forelse($jirau->project?->steps ?? [] as $step)
                    <li>
                        <span>
                            <strong>{{ $step->title }}</strong>
                            <small>
                                {{ $step->is_completed ? 'Concluída' : 'Aberta' }}
                                @if($step->jirauCard)
                                    · JR-{{ $step->jirauCard->id }}
                                    @if((int) $step->jirauCard->jirau_id !== (int) $jirau->id)
                                        em outro quadro
                                    @endif
                                @else
                                    · solta
                                @endif
                            </small>
                        </span>
                    </li>
                @empty
                    <li class="jirau-members__empty">Ainda sem etapas neste projeto.</li>
                @endforelse
            </ul>
            <div class="jirau-members__forms">
                <form method="POST" action="{{ route('jirau.steps.store', $jirau) }}" class="jirau-compose">
                    @csrf
                    <h3>Nova etapa</h3>
                    <x-ui.input name="title" label="Título da etapa" required />
                    <x-ui.input type="textarea" name="notes" label="Observações" rows="2" />
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="create_card" value="0">
                        <input type="checkbox" name="create_card" value="1" checked>
                        Também criar cartão neste quadro
                    </label>
                    <button type="submit" class="pm-btn pm-btn--primary">Criar etapa</button>
                </form>
                <form method="POST" action="{{ route('jirau.steps.import', $jirau) }}" class="jirau-compose">
                    @csrf
                    <h3>Juntar etapas soltas</h3>
                    <p>{{ $unlinkedSteps->count() }} etapa{{ $unlinkedSteps->count() === 1 ? '' : 's' }} sem cartão. Elas entram na faixa de planejamento (ou Encerrado, se já concluídas).</p>
                    <button type="submit" class="pm-btn pm-btn--ghost" @disabled($unlinkedSteps->isEmpty())>Trazer para este Jirau</button>
                </form>
            </div>
        </section>

        @if($jirau->project)
            <form method="POST" action="{{ route('admin.projects.jirau.store', $jirau->project) }}" class="jirau-compose" data-jirau-panel="new-board" hidden>
                @csrf
                <h2>Novo Jirau neste projeto</h2>
                <p>Um projeto pode ter vários quadros — por exemplo desenvolvimento e consultoria em paralelo.</p>
                <x-ui.input name="name" label="Nome (opcional)" />
                <fieldset class="jirau-templates">
                    <legend>Modelo</legend>
                    @foreach($templates as $template)
                        <label class="jirau-template">
                            <input type="radio" name="template" value="{{ $template->value }}" @checked($template === \App\Enums\JirauTemplate::Development) required>
                            <span>
                                <strong>{{ $template->label() }}</strong>
                                <small>{{ $template->description() }}</small>
                            </span>
                        </label>
                    @endforeach
                </fieldset>
                <button type="submit" class="pm-btn pm-btn--primary">Criar quadro</button>
            </form>
        @endif

        <section class="jirau-members" data-jirau-panel="members" @if(! $errors->hasAny(['name', 'username', 'email', 'password', 'login'])) hidden @endif>
            <h2>Pessoas do Jirau</h2>
            <p>Membros entram só neste quadro. Podem criar demandas, sem alocar faixa nem estimativa.</p>
            <ul class="jirau-members__list">
                @forelse($jirau->members->sortBy('name') as $member)
                    <li>
                        <span>
                            <strong>{{ $member->name }}</strong>
                            <small>{{ '@'.$member->username }} · {{ $member->email }}</small>
                        </span>
                        <form method="POST" action="{{ route('jirau.members.destroy', [$jirau, $member]) }}" data-confirm="Remover este membro do Jirau?">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="pm-card__btn pm-card__btn--danger">Remover</button>
                        </form>
                    </li>
                @empty
                    <li class="jirau-members__empty">Ainda sem membros de projeto.</li>
                @endforelse
            </ul>

            <div class="jirau-members__forms">
                <form method="POST" action="{{ route('jirau.members.store', $jirau) }}" class="jirau-compose">
                    @csrf
                    <input type="hidden" name="mode" value="create">
                    <h3>Criar usuário do projeto</h3>
                    <x-ui.input name="name" label="Nome" required />
                    <x-ui.input name="username" label="Usuário" required />
                    <x-ui.input type="email" name="email" label="E-mail" required />
                    <x-ui.input type="password" name="password" label="Senha" required />
                    <x-ui.input type="password" name="password_confirmation" label="Confirmar senha" required />
                    <button type="submit" class="pm-btn pm-btn--primary">Criar e convidar</button>
                </form>
                <form method="POST" action="{{ route('jirau.members.store', $jirau) }}" class="jirau-compose">
                    @csrf
                    <input type="hidden" name="mode" value="attach">
                    <h3>Convidar existente</h3>
                    <x-ui.input name="login" label="Usuário ou e-mail" required />
                    <button type="submit" class="pm-btn pm-btn--ghost">Adicionar ao Jirau</button>
                </form>
            </div>
        </section>
    @endif

    <div class="jirau-board" data-jirau-lanes>
        @foreach($jirau->columns as $column)
            @include('jirau.partials.column')
        @endforeach
    </div>
</div>

@if($openCard)
    @include('jirau.partials.drawer', ['card' => $openCard])
@endif
@endsection
