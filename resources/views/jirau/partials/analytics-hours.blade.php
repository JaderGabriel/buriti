@php
    $hours = $hours ?? [
        'total_label' => '0s',
        'sessions' => 0,
        'running' => 0,
        'people' => 0,
        'cards' => 0,
        'avg_session_label' => '0s',
        'avg_person_label' => '0s',
        'by_user' => [],
        'by_project' => [],
        'by_company' => [],
        'by_column' => [],
        'entries' => [],
    ];
    $report = $filters['relatorio'] ?? 'pessoas';
    $reportLink = fn (string $relatorio) => route('jirau.analytics', array_filter([...$filters, 'aba' => 'horas', 'relatorio' => $relatorio], fn ($value) => $value !== null && $value !== ''));
    $tables = [
        'pessoas' => ['title' => 'Por pessoa', 'hint' => 'Quem apontou horas no recorte', 'rows' => $hours['by_user'], 'name' => 'Pessoa'],
        'projetos' => ['title' => 'Por projeto', 'hint' => 'Tempo acumulado em cada projeto', 'rows' => $hours['by_project'], 'name' => 'Projeto'],
        'empresas' => ['title' => 'Por empresa', 'hint' => 'Contrato / cliente do quadro', 'rows' => $hours['by_company'], 'name' => 'Empresa'],
        'etapas' => ['title' => 'Por etapa', 'hint' => 'Tempo em cada coluna do Jirau', 'rows' => $hours['by_column'], 'name' => 'Etapa'],
    ];
@endphp

<section class="jirau-analytics__kpis" aria-label="Horas">
    @foreach ([
        ['Total', $hours['total_label'], 'open', 'bi', 'apontado no recorte'],
        ['Sessões', $hours['sessions'], 'wip', 'flow', 'inícios de Fazer'],
        ['Pessoas', $hours['people'], 'inbox', 'users', 'quem trabalhou'],
        ['Cartões', $hours['cards'], 'done', 'task', 'com apontamento'],
        ['Em curso', $hours['running'], 'overdue', 'play', 'sessões abertas'],
        ['Média', $hours['avg_person_label'], 'flow', 'operate', 'por pessoa'],
    ] as [$label, $value, $tone, $icon, $hint])
        <article class="jirau-kpi jirau-kpi--{{ $tone }}">
            <span class="jirau-kpi__label">{{ $label }}</span>
            <strong class="jirau-kpi__value">{{ $value }}</strong>
            <span class="jirau-kpi__hint">{{ $hint }}</span>
            <span class="jirau-kpi__icon" aria-hidden="true"><x-ui.icon :name="$icon" class="h-4 w-4" /></span>
        </article>
    @endforeach
</section>

<ul class="jirau-analytics__pulse">
    <li><strong>{{ $hours['avg_session_label'] }}</strong> por sessão</li>
    <li><strong>{{ $hours['avg_person_label'] }}</strong> por pessoa</li>
    <li><strong>{{ $hours['running'] }}</strong> em curso agora</li>
</ul>

<nav class="jirau-report-tabs jirau-report-tabs--sub" aria-label="Relatórios de horas">
    @foreach(['pessoas' => 'Pessoas', 'projetos' => 'Projetos', 'empresas' => 'Empresas', 'etapas' => 'Etapas', 'extrato' => 'Extrato'] as $key => $label)
        <a href="{{ $reportLink($key) }}" @class(['is-on' => $report === $key])>{{ $label }}</a>
    @endforeach
</nav>

@if($report === 'extrato')
    <section class="jirau-analytics__card jirau-analytics__card--span">
        <header class="jirau-analytics__card-head">
            <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="calendar" class="h-5 w-5" /></span>
            <div>
                <p>Extrato</p>
                <h2>Sessões apontadas</h2>
            </div>
        </header>
        <div class="jirau-analytics__table-wrap">
            <table class="jirau-analytics__table">
                <thead>
                    <tr>
                        <th>Pessoa</th>
                        <th>Cartão</th>
                        <th>Projeto</th>
                        <th>Empresa</th>
                        <th>Etapa</th>
                        <th>Início</th>
                        <th>Encerrado</th>
                        <th>Tempo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($hours['entries'] as $row)
                        <tr @class(['is-running' => $row['running']])>
                            <td>{{ $row['user'] }}</td>
                            <td>
                                <a href="{{ route('jirau.show', ['jirau' => $row['jirau_id'], 'cartao' => $row['card_id']]) }}">{{ $row['title'] }}</a>
                            </td>
                            <td>{{ $row['project'] }}</td>
                            <td>{{ $row['company'] }}</td>
                            <td>{{ $row['column'] }}</td>
                            <td>{{ $row['started'] }}</td>
                            <td>{{ $row['ended'] }}</td>
                            <td>{{ $row['label'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">Ainda sem apontamento neste recorte.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($hours['entries'] !== [])
                    <tfoot>
                        <tr class="jirau-time-table__total">
                            <th colspan="7">Total</th>
                            <td>{{ $hours['total_label'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@else
    @php $table = $tables[$report] ?? $tables['pessoas']; @endphp
    <section class="jirau-analytics__card jirau-analytics__card--span">
        <header class="jirau-analytics__card-head">
            <span class="jirau-analytics__card-icon" aria-hidden="true"><x-ui.icon name="bi" class="h-5 w-5" /></span>
            <div>
                <p>Relatório</p>
                <h2>{{ $table['title'] }}</h2>
            </div>
        </header>
        <p class="jirau-analytics__sub">{{ $table['hint'] }}</p>
        <div class="jirau-analytics__table-wrap">
            <table class="jirau-analytics__table">
                <thead>
                    <tr>
                        <th>{{ $table['name'] }}</th>
                        <th>Tempo</th>
                        <th>Sessões</th>
                        <th>Pessoas</th>
                        <th>Cartões</th>
                        <th>Em curso</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($table['rows'] as $row)
                        <tr>
                            <td>{{ $row['name'] }}</td>
                            <td>{{ $row['label'] }}</td>
                            <td>{{ $row['sessions'] }}</td>
                            <td>{{ $row['people'] }}</td>
                            <td>{{ $row['cards'] }}</td>
                            <td>{{ $row['running'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Ainda sem apontamento neste recorte.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($table['rows'] !== [])
                    <tfoot>
                        <tr class="jirau-time-table__total">
                            <th>Total</th>
                            <td>{{ $hours['total_label'] }}</td>
                            <td>{{ $hours['sessions'] }}</td>
                            <td>{{ $hours['people'] }}</td>
                            <td>{{ $hours['cards'] }}</td>
                            <td>{{ $hours['running'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endif
