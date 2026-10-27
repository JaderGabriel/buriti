@extends('layouts.jirau')

@section('content')
@php
    $maxColumn = max(1, collect($metrics['columns'])->max('count') ?: 1);
    $maxType = max(1, collect($metrics['by_type'])->max() ?: 1);
    $maxPriority = max(1, collect($metrics['by_priority'])->max() ?: 1);
    $filters = $filters ?? [];
    $hasFilters = collect($filters)->except('estado')->contains(fn ($value) => $value !== null && $value !== '')
        || ($filters['estado'] ?? 'ativos') !== 'ativos';
@endphp
<div class="jirau-analytics">
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">{{ $jirau ? 'Jirau · '.$jirau->project?->name : 'Jirau' }}</p>
            <h1 class="pm-workspace__title">{{ $jirau ? $jirau->name : 'Analítica' }}</h1>
            <p class="pm-workspace__lead">
                Leitura só do admin: recorte por projeto, responsável, quadro e riscos.
                @if($jirau)
                    Fluxo «{{ $jirau->template?->label() ?? 'quadro' }}».
                @endif
            </p>
        </div>
        <div class="jirau-workspace__actions">
            @if($jirau)
                <a href="{{ route('jirau.show', $jirau) }}" class="pm-btn pm-btn--ghost">Abrir quadro</a>
            @endif
            <a href="{{ route('jirau.home') }}" class="pm-btn pm-btn--ghost">Quadros</a>
        </div>
    </div>

    <form method="GET" action="{{ route('jirau.analytics') }}" class="jirau-filters jirau-filters--analytics">
        <label>
            <span>Projeto</span>
            <select name="projeto" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((int) ($filters['projeto'] ?? 0) === (int) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Quadro</span>
            <select name="quadro" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($boardOptions as $board)
                    <option value="{{ $board->id }}" @selected((int) ($filters['quadro'] ?? 0) === (int) $board->id)>
                        {{ $board->project?->name ? $board->project->name.' · ' : '' }}{{ $board->name }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Responsável</span>
            <select name="usuario" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="none" @selected(($filters['usuario'] ?? null) === 'none')>Sem responsável</option>
                @foreach($people as $person)
                    <option value="{{ $person->id }}" @selected((string) ($filters['usuario'] ?? '') === (string) $person->id)>
                        {{ $person->name }} · {{ $person->jirauTeamLabel() }}
                    </option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Modelo</span>
            <select name="modelo" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($templates as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['modelo'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Estado</span>
            <select name="estado" class="jirau-input" onchange="this.form.submit()">
                <option value="ativos" @selected(($filters['estado'] ?? 'ativos') === 'ativos')>Ativos</option>
                <option value="inativos" @selected(($filters['estado'] ?? '') === 'inativos')>Inativos</option>
                <option value="todos" @selected(($filters['estado'] ?? '') === 'todos')>Todos</option>
            </select>
        </label>
        <label>
            <span>Tipo</span>
            <select name="tipo" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['tipo'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Prioridade</span>
            <select name="prioridade" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todas</option>
                @foreach($priorities as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['prioridade'] ?? null) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Risco</span>
            <select name="risco" class="jirau-input" onchange="this.form.submit()">
                <option value="">Todos</option>
                <option value="atraso" @selected(($filters['risco'] ?? null) === 'atraso')>Prazo vencido</option>
                <option value="sem_responsavel" @selected(($filters['risco'] ?? null) === 'sem_responsavel')>Sem responsável</option>
                <option value="bloqueado" @selected(($filters['risco'] ?? null) === 'bloqueado')>Bloqueados</option>
            </select>
        </label>
        @if($hasFilters)
            <a href="{{ route('jirau.analytics') }}" class="pm-btn pm-btn--ghost jirau-filters__clear">Limpar filtros</a>
        @endif
    </form>

    <div class="dash-stats jirau-analytics__stats">
        @foreach ([
            ['Abertos', $metrics['open'], 'projects', 'jirau'],
            ['Inbox', $metrics['inbox'], 'messages', 'mail'],
            ['Em curso', $metrics['wip'], 'tasks', 'flow'],
            ['Feito', $metrics['done'], 'contacts', 'task'],
            ['Atrasados', $metrics['overdue'], 'opps', 'calendar'],
            ['Fluxo', $metrics['flow_percent'] === null ? '—' : $metrics['flow_percent'].'%', 'projects', 'bi'],
        ] as [$label, $value, $tone, $icon])
            <div class="dash-stat dash-stat--{{ $tone }}">
                <span class="dash-stat__icon" aria-hidden="true">
                    <x-ui.icon :name="$icon" class="h-4 w-4" />
                </span>
                <span class="dash-stat__label">{{ $label }}</span>
                <span class="dash-stat__value">{{ $value }}</span>
            </div>
        @endforeach
    </div>

    <div class="jirau-analytics__meta">
        <p>{{ $metrics['created_7d'] }} novo{{ $metrics['created_7d'] === 1 ? '' : 's' }} em 7 dias</p>
        <p>{{ $metrics['done_7d'] }} concluído{{ $metrics['done_7d'] === 1 ? '' : 's' }} em 7 dias</p>
        <p>Lead time {{ $metrics['lead_time_days'] === null ? '—' : $metrics['lead_time_days'].' d' }}</p>
        <p>Estimativa aberta {{ $metrics['estimate_open'] }} · feita {{ $metrics['estimate_done'] }}</p>
        <p>Demandas {{ $metrics['demands'] }} · bloqueados {{ $metrics['blocked'] }} · sem responsável {{ $metrics['unassigned'] }}</p>
        @if($metrics['inbox_oldest_days'] !== null)
            <p>Inbox mais antigo: {{ $metrics['inbox_oldest_days'] }} dia{{ $metrics['inbox_oldest_days'] === 1 ? '' : 's' }}</p>
        @endif
        @if($metrics['steps']['total'] > 0)
            <p>Etapas {{ $metrics['steps']['done'] }}/{{ $metrics['steps']['total'] }} ({{ $metrics['steps']['percent'] }}%) · {{ $metrics['steps']['unlinked'] }} solta{{ $metrics['steps']['unlinked'] === 1 ? '' : 's' }}</p>
        @endif
    </div>

    @if($metrics['wip_breaches'] !== [])
        <section class="jirau-analytics__alert" role="status">
            <h2>WIP estourado</h2>
            <ul>
                @foreach($metrics['wip_breaches'] as $breach)
                    <li>
                        <a href="{{ route('jirau.show', $breach['jirau_id']) }}">{{ $breach['jirau_name'] }}</a>
                        — {{ $breach['column'] }} com {{ $breach['count'] }}/{{ $breach['limit'] }}
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="dash-panels jirau-analytics__panels">
        <section class="dash-panel">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="flow" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Fluxo</p>
                        <h2 class="dash-panel__title">{{ $jirau ? 'Faixas deste quadro' : 'Inbox → curso → feito' }}</h2>
                    </div>
                </div>
            </header>
            @if($metrics['columns'] === [])
                <p class="jirau-analytics__empty">Ainda sem cartões para desenhar o fluxo.</p>
            @else
                <ul class="jirau-bars" aria-label="Distribuição por faixa">
                    @foreach($metrics['columns'] as $column)
                        <li @class(['is-over' => $column['over']])>
                            <div class="jirau-bars__label">
                                <span>{{ $column['name'] }}</span>
                                <strong>{{ $column['count'] }}@if($column['wip_limit']) / {{ $column['wip_limit'] }}@endif</strong>
                            </div>
                            <div class="jirau-bars__track">
                                <span style="width: {{ round(($column['count'] / $maxColumn) * 100) }}%; background: {{ $column['color'] }}"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="dash-panel">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="task" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Composição</p>
                        <h2 class="dash-panel__title">Tipo e prioridade</h2>
                    </div>
                </div>
            </header>
            <div class="jirau-analytics__split">
                <ul class="jirau-bars jirau-bars--compact">
                    @foreach($types as $value => $label)
                        <li>
                            <div class="jirau-bars__label">
                                <span>{{ $label }}</span>
                                <strong>{{ $metrics['by_type'][$value] ?? 0 }}</strong>
                            </div>
                            <div class="jirau-bars__track">
                                <span style="width: {{ round((($metrics['by_type'][$value] ?? 0) / $maxType) * 100) }}%"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <ul class="jirau-bars jirau-bars--compact">
                    @foreach($priorities as $value => $label)
                        <li>
                            <div class="jirau-bars__label">
                                <span>{{ $label }}</span>
                                <strong>{{ $metrics['by_priority'][$value] ?? 0 }}</strong>
                            </div>
                            <div class="jirau-bars__track">
                                <span class="is-{{ $value }}" style="width: {{ round((($metrics['by_priority'][$value] ?? 0) / $maxPriority) * 100) }}%"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="dash-panel">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Riscos</p>
                        <h2 class="dash-panel__title">Atrasados e envelhecimento</h2>
                    </div>
                </div>
            </header>
            <h3 class="jirau-analytics__sub">Prazo vencido</h3>
            <ul class="jirau-analytics__list">
                @forelse($metrics['overdue_cards'] as $card)
                    <li>
                        <a href="{{ route('jirau.show', ['jirau' => $card['jirau_id'], 'cartao' => $card['id']]) }}">{{ $card['title'] }}</a>
                        <small>{{ $card['due_at'] }} · {{ $card['days'] }}d</small>
                    </li>
                @empty
                    <li class="text-mist">Nenhum cartão atrasado.</li>
                @endforelse
            </ul>
            <h3 class="jirau-analytics__sub">Mais antigos em aberto</h3>
            <ul class="jirau-analytics__list">
                @forelse($metrics['aging'] as $card)
                    <li>
                        <a href="{{ route('jirau.show', ['jirau' => $card['jirau_id'], 'cartao' => $card['id']]) }}">{{ $card['title'] }}</a>
                        <small>{{ $card['column'] }} · {{ $card['days'] }}d</small>
                    </li>
                @empty
                    <li class="text-mist">Nada parado no fluxo.</li>
                @endforelse
            </ul>
        </section>
    </div>

    <div class="dash-panels jirau-analytics__panels jirau-analytics__panels--wide">
        <section class="dash-panel">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="users" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Pessoas</p>
                        <h2 class="dash-panel__title">Carga por responsável</h2>
                    </div>
                </div>
            </header>
            <ul class="jirau-analytics__list">
                @forelse($metrics['assignees'] as $row)
                    <li>
                        <a href="{{ route('jirau.analytics', array_filter([...$filters, 'usuario' => $row['id'] ?? 'none'])) }}">{{ $row['name'] }}</a>
                        <small>{{ $row['open'] }} abertos · {{ $row['done'] }} feitos</small>
                    </li>
                @empty
                    <li class="text-mist">Sem cartões para agrupar.</li>
                @endforelse
            </ul>
        </section>

        @if(($metrics['project_rows'] ?? []) !== [] && ! ($filters['projeto'] ?? null))
            <section class="dash-panel">
                <header class="dash-panel__head">
                    <div class="dash-panel__title-wrap">
                        <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="project" class="h-5 w-5" /></span>
                        <div>
                            <p class="dash-panel__eyebrow">Projetos</p>
                            <h2 class="dash-panel__title">Por projeto</h2>
                        </div>
                    </div>
                </header>
                <div class="jirau-analytics__table-wrap">
                    <table class="jirau-analytics__table">
                        <thead>
                            <tr>
                                <th>Projeto</th>
                                <th>Quadros</th>
                                <th>Abertos</th>
                                <th>Inbox</th>
                                <th>Feito</th>
                                <th>Atraso</th>
                                <th>Fluxo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metrics['project_rows'] as $row)
                                <tr>
                                    <td>
                                        @if($row['id'])
                                            <a href="{{ route('jirau.analytics', array_filter([...$filters, 'projeto' => $row['id']])) }}">{{ $row['name'] }}</a>
                                        @else
                                            {{ $row['name'] }}
                                        @endif
                                    </td>
                                    <td>{{ $row['boards'] }}</td>
                                    <td>{{ $row['open'] }}</td>
                                    <td>{{ $row['inbox'] }}</td>
                                    <td>{{ $row['done'] }}</td>
                                    <td>{{ $row['overdue'] }}</td>
                                    <td>{{ $row['flow_percent'] === null ? '—' : $row['flow_percent'].'%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if(! ($filters['quadro'] ?? null))
            <section class="dash-panel">
                <header class="dash-panel__head">
                    <div class="dash-panel__title-wrap">
                        <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="jirau" class="h-5 w-5" /></span>
                        <div>
                            <p class="dash-panel__eyebrow">Quadros</p>
                            <h2 class="dash-panel__title">Por Jirau</h2>
                        </div>
                    </div>
                </header>
                <div class="jirau-analytics__table-wrap">
                    <table class="jirau-analytics__table">
                        <thead>
                            <tr>
                                <th>Quadro</th>
                                <th>Abertos</th>
                                <th>Inbox</th>
                                <th>Feito</th>
                                <th>Atraso</th>
                                <th>Fluxo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($metrics['board_rows'] as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('jirau.analytics', array_filter([...$filters, 'quadro' => $row['id']])) }}">{{ $row['name'] }}</a>
                                        <small>{{ $row['project'] }} · {{ $row['template'] }}</small>
                                    </td>
                                    <td>{{ $row['open'] }}</td>
                                    <td>{{ $row['inbox'] }}</td>
                                    <td>{{ $row['done'] }}</td>
                                    <td>{{ $row['overdue'] }}</td>
                                    <td>{{ $row['flow_percent'] === null ? '—' : $row['flow_percent'].'%' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">Nenhum Jirau ainda.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
