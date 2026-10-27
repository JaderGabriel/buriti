@extends('layouts.jirau')

@section('content')
@php
    $maxColumn = max(1, collect($metrics['columns'])->max('count') ?: 1);
    $maxType = max(1, collect($metrics['by_type'])->max() ?: 1);
    $maxPriority = max(1, collect($metrics['by_priority'])->max() ?: 1);
    $maxAssignee = max(1, collect($metrics['assignees'])->max('open') ?: 1);
    $filters = $filters ?? [];
    $hasFilters = collect($filters)->except('estado')->contains(fn ($value) => $value !== null && $value !== '')
        || ($filters['estado'] ?? 'ativos') !== 'ativos';
    $bar = fn ($n, $max) => max(0, min(100, (int) round(((int) $n / max(1, (int) $max)) * 100)));
    $initials = function (string $name): string {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return mb_strtoupper(collect($parts)->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode(''));
    };
@endphp
<div class="jirau-analytics">
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">{{ $jirau ? 'Jirau · '.$jirau->project?->name : 'Jirau' }}</p>
            <h1 class="pm-workspace__title">Analítica</h1>
            <p class="pm-workspace__lead">
                Leitura só do admin: recorte por projeto, responsável, quadro e riscos.
                @if($jirau)
                    Recorte do quadro «{{ $jirau->name }}». Fluxo «{{ $jirau->template?->label() ?? 'quadro' }}».
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
        <p class="jirau-analytics__controls-kicker">Recortar leitura</p>
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

    <section class="jirau-analytics__kpis" aria-label="Indicadores">
        @foreach ([
            ['Abertos', $metrics['open'], 'open', 'jirau', 'vivos no recorte'],
            ['Inbox', $metrics['inbox'], 'inbox', 'mail', $metrics['inbox_oldest_days'] !== null ? 'mais antigo '.$metrics['inbox_oldest_days'].'d' : 'triagem'],
            ['Em curso', $metrics['wip'], 'wip', 'flow', 'entre faixas'],
            ['Feito', $metrics['done'], 'done', 'task', 'no recorte'],
            ['Atrasados', $metrics['overdue'], 'overdue', 'calendar', 'prazo vencido'],
            ['Fluxo', $metrics['flow_percent'] === null ? '—' : $metrics['flow_percent'].'%', 'flow', 'bi', 'concluído / aberto'],
        ] as [$label, $value, $tone, $icon, $hint])
            <article @class(['jirau-kpi', 'jirau-kpi--'.$tone, 'is-hot' => $tone === 'overdue' && (int) $metrics['overdue'] > 0])>
                <span class="jirau-kpi__label">{{ $label }}</span>
                <strong class="jirau-kpi__value">{{ $value }}</strong>
                <span class="jirau-kpi__hint">{{ $hint }}</span>
                @if($tone === 'flow' && $metrics['flow_percent'] !== null)
                    <span class="jirau-kpi__ring" style="--p: {{ (int) $metrics['flow_percent'] }}" aria-hidden="true"></span>
                @else
                    <span class="jirau-kpi__icon" aria-hidden="true"><x-ui.icon :name="$icon" class="h-4 w-4" /></span>
                @endif
            </article>
        @endforeach
    </section>

    <ul class="jirau-analytics__pulse">
        <li><strong>{{ $metrics['created_7d'] }}</strong> novo{{ $metrics['created_7d'] === 1 ? '' : 's' }} em 7 dias</li>
        <li><strong>{{ $metrics['done_7d'] }}</strong> concluído{{ $metrics['done_7d'] === 1 ? '' : 's' }} em 7 dias</li>
        <li><strong>{{ $metrics['lead_time_days'] === null ? '—' : $metrics['lead_time_days'].' d' }}</strong> lead time</li>
        <li><strong>{{ $metrics['estimate_open'] }}</strong> pts abertos · <strong>{{ $metrics['estimate_done'] }}</strong> feitos</li>
        <li><strong>{{ $metrics['demands'] }}</strong> demandas · <strong>{{ $metrics['blocked'] }}</strong> bloqueados · <strong>{{ $metrics['unassigned'] }}</strong> sem responsável</li>
        @if($metrics['steps']['total'] > 0)
            <li><strong>{{ $metrics['steps']['done'] }}/{{ $metrics['steps']['total'] }}</strong> etapas ({{ $metrics['steps']['percent'] }}%) · {{ $metrics['steps']['unlinked'] }} solta{{ $metrics['steps']['unlinked'] === 1 ? '' : 's' }}</li>
        @endif
    </ul>

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

    <div class="jirau-analytics__stage">
        <section class="jirau-analytics__card">
            <header class="jirau-analytics__card-head">
                <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="flow" class="h-5 w-5" /></span>
                <div>
                    <p>Fluxo</p>
                    <h2>{{ $jirau ? 'Faixas deste quadro' : 'Inbox → curso → feito' }}</h2>
                </div>
            </header>
            @if($metrics['columns'] === [])
                <p class="jirau-analytics__empty">Ainda sem cartões para desenhar o fluxo.</p>
            @else
                <ul class="jirau-lanes" aria-label="Distribuição por faixa">
                    @foreach($metrics['columns'] as $column)
                        <li @class(['jirau-lane', 'is-over' => $column['over']]) style="--lane: {{ $column['color'] }}">
                            <span class="jirau-lane__swatch" aria-hidden="true"></span>
                            <div class="jirau-lane__copy">
                                <span>{{ $column['name'] }}</span>
                                <strong>{{ $column['count'] }}@if($column['wip_limit']) / {{ $column['wip_limit'] }}@endif</strong>
                            </div>
                            <div class="jirau-lane__track">
                                <span style="width: {{ $bar($column['count'], $maxColumn) }}%"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="jirau-analytics__card">
            <header class="jirau-analytics__card-head">
                <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="task" class="h-5 w-5" /></span>
                <div>
                    <p>Composição</p>
                    <h2>Tipo e prioridade</h2>
                </div>
            </header>
            <div class="jirau-analytics__split">
                <div>
                    <p class="jirau-analytics__sub">Tipo</p>
                    <div class="jirau-stack" aria-hidden="true">
                        @foreach($types as $value => $label)
                            @if(($metrics['by_type'][$value] ?? 0) > 0)
                                <span class="is-{{ $value }}" style="flex: {{ $metrics['by_type'][$value] }}"></span>
                            @endif
                        @endforeach
                    </div>
                    <ul class="jirau-meters">
                        @foreach($types as $value => $label)
                            <li>
                                <div class="jirau-meters__label">
                                    <i class="is-{{ $value }}" aria-hidden="true"></i>
                                    <span>{{ $label }}</span>
                                    <strong>{{ $metrics['by_type'][$value] ?? 0 }}</strong>
                                </div>
                                <div class="jirau-meters__track">
                                    <span class="is-{{ $value }}" style="width: {{ $bar($metrics['by_type'][$value] ?? 0, $maxType) }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p class="jirau-analytics__sub">Prioridade</p>
                    <div class="jirau-stack" aria-hidden="true">
                        @foreach($priorities as $value => $label)
                            @if(($metrics['by_priority'][$value] ?? 0) > 0)
                                <span class="is-{{ $value }}" style="flex: {{ $metrics['by_priority'][$value] }}"></span>
                            @endif
                        @endforeach
                    </div>
                    <ul class="jirau-meters">
                        @foreach($priorities as $value => $label)
                            <li>
                                <div class="jirau-meters__label">
                                    <i class="is-{{ $value }}" aria-hidden="true"></i>
                                    <span>{{ $label }}</span>
                                    <strong>{{ $metrics['by_priority'][$value] ?? 0 }}</strong>
                                </div>
                                <div class="jirau-meters__track">
                                    <span class="is-{{ $value }}" style="width: {{ $bar($metrics['by_priority'][$value] ?? 0, $maxPriority) }}%"></span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="jirau-analytics__card jirau-analytics__card--span">
            <header class="jirau-analytics__card-head">
                <span class="jirau-analytics__card-icon is-warn" aria-hidden="true"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
                <div>
                    <p>Riscos</p>
                    <h2>Atrasados e envelhecimento</h2>
                </div>
            </header>
            <div class="jirau-analytics__risks">
                <div>
                    <h3 class="jirau-analytics__sub">Prazo vencido</h3>
                    <ul class="jirau-risks">
                        @forelse($metrics['overdue_cards'] as $card)
                            <li>
                                <span class="jirau-risks__days is-late">{{ $card['days'] }}d</span>
                                <div>
                                    <a href="{{ route('jirau.show', ['jirau' => $card['jirau_id'], 'cartao' => $card['id']]) }}">{{ $card['title'] }}</a>
                                    <small>{{ $card['due_at'] }}</small>
                                </div>
                            </li>
                        @empty
                            <li class="jirau-risks__empty">Nenhum cartão atrasado.</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <h3 class="jirau-analytics__sub">Mais antigos em aberto</h3>
                    <ul class="jirau-risks">
                        @forelse($metrics['aging'] as $card)
                            <li>
                                <span class="jirau-risks__days">{{ $card['days'] }}d</span>
                                <div>
                                    <a href="{{ route('jirau.show', ['jirau' => $card['jirau_id'], 'cartao' => $card['id']]) }}">{{ $card['title'] }}</a>
                                    <small>{{ $card['column'] }}</small>
                                </div>
                            </li>
                        @empty
                            <li class="jirau-risks__empty">Nada parado no fluxo.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </section>
    </div>

    <div class="jirau-analytics__stage jirau-analytics__stage--wide">
        <section class="jirau-analytics__card">
            <header class="jirau-analytics__card-head">
                <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="users" class="h-5 w-5" /></span>
                <div>
                    <p>Pessoas</p>
                    <h2>Carga por responsável</h2>
                </div>
            </header>
            <ul class="jirau-load">
                @forelse($metrics['assignees'] as $row)
                    <li>
                        <span class="jirau-load__avatar" aria-hidden="true">{{ $initials($row['name']) }}</span>
                        <div class="jirau-load__body">
                            <div class="jirau-load__label">
                                <a href="{{ route('jirau.analytics', array_filter([...$filters, 'usuario' => $row['id'] ?? 'none'])) }}">{{ $row['name'] }}</a>
                                <small>{{ $row['open'] }} abertos · {{ $row['done'] }} feitos</small>
                            </div>
                            <div class="jirau-load__track">
                                <span style="width: {{ $bar($row['open'], $maxAssignee) }}%"></span>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="jirau-analytics__empty">Sem cartões para agrupar.</li>
                @endforelse
            </ul>
        </section>

        @if(($metrics['project_rows'] ?? []) !== [] && ! ($filters['projeto'] ?? null))
            <section class="jirau-analytics__card">
                <header class="jirau-analytics__card-head">
                    <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="project" class="h-5 w-5" /></span>
                    <div>
                        <p>Projetos</p>
                        <h2>Por projeto</h2>
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
                                    <td @class(['is-late' => (int) $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                                    <td>
                                        <span class="jirau-flowpct">
                                            <i style="width: {{ (int) ($row['flow_percent'] ?? 0) }}%"></i>
                                            <em>{{ $row['flow_percent'] === null ? '—' : $row['flow_percent'].'%' }}</em>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if(! ($filters['quadro'] ?? null))
            <section class="jirau-analytics__card jirau-analytics__card--span">
                <header class="jirau-analytics__card-head">
                    <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="jirau" class="h-5 w-5" /></span>
                    <div>
                        <p>Quadros</p>
                        <h2>Por Jirau</h2>
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
                                    <td @class(['is-late' => (int) $row['overdue'] > 0])>{{ $row['overdue'] }}</td>
                                    <td>
                                        <span class="jirau-flowpct">
                                            <i style="width: {{ (int) ($row['flow_percent'] ?? 0) }}%"></i>
                                            <em>{{ $row['flow_percent'] === null ? '—' : $row['flow_percent'].'%' }}</em>
                                        </span>
                                    </td>
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
