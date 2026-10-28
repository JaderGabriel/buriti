@extends('layouts.admin')

@section('content')
    @php
        $health = $dashboard['health'];
        $trendMax = max(1, collect($dashboard['trend'])->max('total') ?: 0);
        $lastByCommand = $dashboard['last_by_command'];
        $firstGroup = $groups[0]['id'] ?? 'suite';
        $lastSuite = $dashboard['last_suite'] ?? null;
        $healthIcon = match ($health['tone']) {
            'ok' => 'qualified',
            'warn' => 'lost',
            'run' => 'play',
            default => 'diagnose',
        };
    @endphp

    <div class="qa-lab" data-qa-lab x-data="{ tab: '{{ $firstGroup }}' }">
        <header class="qa-toolbar">
            <div class="qa-toolbar__copy">
                <p class="admin-hero__kicker">Sistema · Qualidade</p>
                <h1 class="qa-toolbar__title">Centro de testes</h1>
            </div>

            <nav class="qa-flow" aria-label="Fluxo do laboratório">
                <a class="qa-flow__step is-done" href="#qa-kpis">
                    <span>1</span> Saúde
                </a>
                <a class="qa-flow__step is-current" href="#qa-console">
                    <span>2</span> Executar
                </a>
                <a class="qa-flow__step" href="#qa-reports">
                    <span>3</span> Relatórios
                </a>
            </nav>

            <div class="qa-toolbar__actions">
                <div class="qa-health qa-health--{{ $health['tone'] }}" title="{{ $health['hint'] }}">
                    <span class="qa-health__icon" aria-hidden="true">
                        <x-ui.icon :name="$healthIcon" class="h-4 w-4" />
                    </span>
                    <div>
                        <strong>{{ $health['label'] }}</strong>
                        <span>7 dias</span>
                    </div>
                </div>
                @if($lastSuite)
                    <a href="{{ route('admin.qa.show', $lastSuite) }}" class="qa-lab__ghost">
                        <x-ui.icon name="proposal" class="h-4 w-4" />
                        Última suíte
                    </a>
                @endif
                <form method="POST" action="{{ route('admin.qa.store') }}" data-confirm="Correr a suíte PHPUnit agora? Pode levar até 30 segundos.">
                    @csrf
                    <input type="hidden" name="command" value="suite.phpunit">
                    <button type="submit" class="qa-lab__run" @disabled($suiteRunning)>
                        <x-ui.icon name="play" class="h-4 w-4" />
                        {{ $suiteRunning ? 'Suíte a correr…' : 'Rodar suíte completa' }}
                    </button>
                </form>
            </div>
        </header>

        <div id="qa-kpis" class="qa-kpis" aria-label="Indicadores de qualidade">
            @foreach ($dashboard['kpis'] as $card)
                <article
                    class="qa-kpi qa-kpi--{{ $card['tone'] }} is-{{ $card['direction'] }}"
                    data-kpi-polarity="{{ $card['polarity'] }}"
                    title="{{ $card['aria'] }}"
                >
                    <div class="qa-kpi__head">
                        <span class="qa-kpi__icon" aria-hidden="true">
                            <x-ui.icon :name="$card['icon']" class="h-4 w-4" />
                        </span>
                        <span class="qa-kpi__label">{{ $card['label'] }}</span>
                    </div>
                    <p class="qa-kpi__value">{{ $card['value'] }}</p>
                    <p class="qa-kpi__delta">{{ $card['delta_label'] }}</p>
                    <svg class="qa-kpi__spark" viewBox="0 0 72 28" aria-hidden="true">
                        <path class="qa-kpi__spark-area" d="{{ $card['area'] }}" />
                        <path class="qa-kpi__spark-line" d="{{ $card['path'] }}" />
                    </svg>
                </article>
            @endforeach
        </div>

        <div class="qa-lab__grid">
            <section id="qa-console" class="dash-panel qa-lab__console" x-ref="console">
                <header class="dash-panel__head">
                    <div class="dash-panel__title-wrap">
                        <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="diagnose" class="h-5 w-5" /></span>
                        <div>
                            <p class="dash-panel__eyebrow">Comandos</p>
                            <h2 class="dash-panel__title">Console operacional</h2>
                        </div>
                    </div>
                </header>

                <div class="qa-lab__tabs" role="tablist" aria-label="Módulos de teste">
                    @foreach ($groups as $group)
                        <button
                            type="button"
                            class="qa-lab__tab"
                            data-tone="{{ $group['id'] }}"
                            role="tab"
                            :class="tab === '{{ $group['id'] }}' && 'is-active'"
                            :aria-selected="tab === '{{ $group['id'] }}'"
                            @click="tab = '{{ $group['id'] }}'"
                        >
                            <x-ui.icon :name="$group['icon'] ?? 'diagnose'" class="h-3.5 w-3.5" />
                            {{ $group['label'] }}
                        </button>
                    @endforeach
                </div>

                @foreach ($groups as $group)
                    <div
                        class="qa-lab__pane"
                        role="tabpanel"
                        data-tone="{{ $group['id'] }}"
                        x-show="tab === '{{ $group['id'] }}'"
                    >
                        <p class="qa-lab__pane-lead">{{ $group['description'] }}</p>
                        <ul class="qa-lab__commands">
                            @foreach ($group['commands'] as $item)
                                @php
                                    $last = $lastByCommand[$item['id']] ?? null;
                                @endphp
                                <li class="qa-command">
                                    <span class="qa-command__icon" aria-hidden="true">
                                        <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                                    </span>
                                    <div class="qa-command__body">
                                        <div class="qa-command__title-row">
                                            <p class="qa-command__title">{{ $item['label'] }}</p>
                                            @if($last)
                                                <span @class([
                                                    'qa-command__chip',
                                                    'qa-command__chip--ok' => $last->isPassed(),
                                                    'qa-command__chip--run' => $last->isRunning(),
                                                    'qa-command__chip--bad' => $last->isFailed(),
                                                ])>{{ $last->statusLabel() }}</span>
                                            @endif
                                        </div>
                                        <p class="qa-command__hint">{{ $item['hint'] }}</p>
                                        @if($last)
                                            <p class="qa-command__meta">
                                                {{ $last->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}
                                                · {{ $last->durationLabel() }}
                                                @if($last->user)
                                                    · {{ $last->user->name }}
                                                @endif
                                            </p>
                                        @endif
                                    </div>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.qa.store') }}"
                                        class="qa-command__action"
                                        @if(! empty($item['confirm'])) data-confirm="{{ $item['confirm'] }}" @endif
                                    >
                                        @csrf
                                        <input type="hidden" name="command" value="{{ $item['id'] }}">
                                        @if($item['kind'] === 'suite' && $suiteRunning)
                                            <button type="submit" class="qa-command__btn" disabled>A correr…</button>
                                        @else
                                            <button type="submit" class="qa-command__btn">Executar</button>
                                        @endif
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </section>

            <aside class="qa-lab__side">
                <section class="dash-panel qa-lab__modules">
                    <header class="dash-panel__head">
                        <div class="dash-panel__title-wrap">
                            <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="bi" class="h-5 w-5" /></span>
                            <div>
                                <p class="dash-panel__eyebrow">Módulos</p>
                                <h2 class="dash-panel__title">Saúde por área</h2>
                            </div>
                        </div>
                    </header>
                    <ul class="qa-modules">
                        @foreach ($dashboard['modules'] as $module)
                            <li>
                                <button
                                    type="button"
                                    class="qa-module qa-module--{{ $module['status'] }}"
                                    data-tone="{{ $module['tone'] }}"
                                    :class="tab === '{{ $module['id'] }}' && 'is-active'"
                                    @click="tab = '{{ $module['id'] }}'; $refs.console.scrollIntoView({ block: 'nearest' })"
                                >
                                    <span class="qa-module__icon" aria-hidden="true">
                                        <x-ui.icon :name="$module['icon']" class="h-4 w-4" />
                                    </span>
                                    <span class="qa-module__copy">
                                        <span class="qa-module__label">{{ $module['label'] }}</span>
                                        <span class="qa-module__meta">
                                            {{ $module['status_label'] }}
                                            @if($module['run'])
                                                · {{ $module['run']->created_at?->timezone(config('app.timezone'))->diffForHumans() }}
                                            @endif
                                        </span>
                                    </span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </section>

                <section class="dash-panel qa-lab__chart">
                    <header class="dash-panel__head">
                        <div class="dash-panel__title-wrap">
                            <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="flow" class="h-5 w-5" /></span>
                            <div>
                                <p class="dash-panel__eyebrow">14 dias</p>
                                <h2 class="dash-panel__title">Volume de execuções</h2>
                            </div>
                        </div>
                    </header>
                    <div class="qa-trend" role="img" aria-label="Volume de execuções nos últimos 14 dias">
                        @foreach ($dashboard['trend'] as $day)
                            @php
                                $okH = (int) round(($day['passed'] / $trendMax) * 100);
                                $badH = (int) round(($day['failed'] / $trendMax) * 100);
                            @endphp
                            <div class="qa-trend__col" title="{{ $day['day'] }}: {{ $day['total'] }} ({{ $day['passed'] }} OK / {{ $day['failed'] }} falhas)">
                                <span class="qa-trend__bar">
                                    @if($day['failed'] > 0)
                                        <span class="qa-trend__fill qa-trend__fill--bad" style="height: {{ max(10, $badH) }}%"></span>
                                    @endif
                                    @if($day['passed'] > 0)
                                        <span class="qa-trend__fill qa-trend__fill--ok" style="height: {{ max(10, $okH) }}%"></span>
                                    @endif
                                    @if($day['total'] === 0)
                                        <span class="qa-trend__fill qa-trend__fill--idle" style="height: 12%"></span>
                                    @endif
                                </span>
                                <span class="qa-trend__label">{{ $day['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            </aside>
        </div>

        <section id="qa-reports" class="dash-panel qa-lab__history" aria-labelledby="qa-reports-title">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="proposal" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Auditoria</p>
                        <h2 id="qa-reports-title" class="dash-panel__title">Relatórios</h2>
                    </div>
                </div>
                <p class="dash-panel__meta">Últimas 40 execuções</p>
            </header>

            @if($runs->isEmpty())
                <p class="qa-lab__empty">Ainda não há execuções. Dispare um comando no console.</p>
            @else
                <div class="qa-lab__table-wrap">
                    <table class="qa-lab__table">
                        <thead>
                            <tr>
                                <th>Estado</th>
                                <th>Comando</th>
                                <th>Resumo</th>
                                <th>Quem</th>
                                <th>Duração</th>
                                <th>Quando</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                <tr>
                                    <td>
                                        <span @class([
                                            'qa-command__chip',
                                            'qa-command__chip--ok' => $run->isPassed(),
                                            'qa-command__chip--run' => $run->isRunning(),
                                            'qa-command__chip--bad' => $run->isFailed(),
                                        ])>{{ $run->statusLabel() }}</span>
                                    </td>
                                    <td>
                                        <a href="{{ route('admin.qa.show', $run) }}" class="qa-lab__link">{{ \App\Support\QaCatalog::label($run->command) }}</a>
                                    </td>
                                    <td class="qa-lab__ellipsis" title="{{ $run->summary }}">{{ $run->summary }}</td>
                                    <td class="qa-lab__who">{{ $run->user?->name ?? '—' }}</td>
                                    <td>{{ $run->durationLabel() }}</td>
                                    <td>{{ $run->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
