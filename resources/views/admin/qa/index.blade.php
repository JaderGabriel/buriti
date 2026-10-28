@extends('layouts.admin')

@section('content')
    @php
        $health = $dashboard['health'];
        $trendMax = max(1, collect($dashboard['trend'])->max('total') ?: 0);
        $lastByCommand = $dashboard['last_by_command'];
        $firstGroup = $groups[0]['id'] ?? 'suite';
        $areaIds = collect($groups)->pluck('id')->values()->all();
        $modulesById = collect($dashboard['modules'])->keyBy('id');
        $cmdAnchor = static fn (string $id): string => 'qa-cmd-'.str_replace('.', '-', $id);
        $lastSuite = $dashboard['last_suite'] ?? null;
        $healthIcon = match ($health['tone']) {
            'ok' => 'qualified',
            'warn' => 'lost',
            'run' => 'play',
            default => 'diagnose',
        };
    @endphp

    <div
        class="qa-lab"
        data-qa-lab
        x-data="{
            areas: {{ \Illuminate\Support\Js::from($areaIds) }},
            tab: '{{ $firstGroup }}',
            section: 'qa-kpis',
            applyHash() {
                const name = (window.location.hash || '').replace(/^#/, '');
                const area = name.match(/^qa-area-([a-z0-9_-]+)$/i);
                const command = name.match(/^qa-cmd-([a-z0-9-]+)$/i);
                if (area && this.areas.includes(area[1])) {
                    this.tab = area[1];
                    this.section = 'qa-console';
                    this.openPane(area[1]);
                    return;
                }
                if (command) {
                    const pane = document.getElementById(name)?.closest('details.qa-pane');
                    const paneId = pane?.id?.replace(/^qa-area-/, '');
                    if (paneId && this.areas.includes(paneId)) {
                        this.tab = paneId;
                    }
                    this.section = 'qa-console';
                    return;
                }
                if (name === 'qa-reports') {
                    this.section = 'qa-reports';
                    return;
                }
                if (name === 'qa-console' || name === 'qa-modules') {
                    this.section = 'qa-console';
                    return;
                }
                this.section = 'qa-kpis';
            },
            openPane(id) {
                const targetId = 'qa-area-' + id;
                this.$root.querySelectorAll('.qa-console details.qa-pane').forEach((el) => {
                    el.open = el.id === targetId;
                });
            },
            expandAll() {
                this.$root.querySelectorAll('.qa-console details.qa-pane').forEach((el) => { el.open = true; });
            },
            collapseAll() {
                this.$root.querySelectorAll('.qa-console details.qa-pane').forEach((el) => { el.open = false; });
            },
        }"
        x-init="applyHash(); window.addEventListener('hashchange', () => applyHash())"
    >
        <header class="qa-toolbar">
            <div class="qa-toolbar__copy">
                <p class="admin-hero__kicker">Sistema · Qualidade</p>
                <h1 class="qa-toolbar__title">Centro de testes</h1>
            </div>

            <nav class="qa-flow" aria-label="Fluxo do laboratório">
                <a class="qa-flow__step" href="#qa-kpis" :class="{ 'is-current': section === 'qa-kpis' }"><span>1</span> Saúde</a>
                <a class="qa-flow__step" href="#qa-console" :class="{ 'is-current': section === 'qa-console' }"><span>2</span> Executar</a>
                <a class="qa-flow__step" href="#qa-reports" :class="{ 'is-current': section === 'qa-reports' }"><span>3</span> Relatórios</a>
            </nav>

            <div class="qa-toolbar__actions">
                <div class="qa-health qa-health--{{ $health['tone'] }}" title="{{ $health['hint'] }}">
                    <span class="qa-health__icon" aria-hidden="true">
                        <x-ui.icon :name="$healthIcon" class="h-4 w-4" />
                    </span>
                    <strong>{{ $health['label'] }}</strong>
                </div>
                @if($lastSuite)
                    <a href="{{ route('admin.qa.show', $lastSuite) }}" class="qa-lab__ghost">
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
                    <div class="qa-kpi__row">
                        <span class="qa-kpi__icon" aria-hidden="true">
                            <x-ui.icon :name="$card['icon']" class="h-3.5 w-3.5" />
                        </span>
                        <span class="qa-kpi__value">{{ $card['value'] }}</span>
                        <span class="qa-kpi__delta">{{ $card['delta_label'] }}</span>
                    </div>
                    <span class="qa-kpi__label">{{ $card['label'] }}</span>
                    <svg class="qa-kpi__spark" viewBox="0 0 72 28" preserveAspectRatio="none" aria-hidden="true">
                        <path class="qa-kpi__spark-area" d="{{ $card['area'] }}" />
                        <path class="qa-kpi__spark-line" d="{{ $card['path'] }}" />
                    </svg>
                </article>
            @endforeach
        </div>

        <div class="qa-workspace">
            <aside id="qa-modules" class="qa-panel" aria-labelledby="qa-areas-title">
                <header class="qa-panel__head">
                    <div>
                        <p class="qa-panel__eyebrow">Sumário</p>
                        <h2 id="qa-areas-title" class="qa-panel__title">Saúde por área</h2>
                    </div>
                </header>
                <nav class="qa-panel__body qa-toc" aria-label="Sumário de testes por área">
                    @foreach ($groups as $group)
                        @php $module = $modulesById[$group['id']] ?? null; @endphp
                        <div
                            class="qa-toc__area qa-navitem--{{ $module['status'] ?? 'idle' }}"
                            data-tone="{{ $group['id'] }}"
                            :class="{ 'is-active': tab === '{{ $group['id'] }}' }"
                        >
                            <a
                                href="#qa-area-{{ $group['id'] }}"
                                class="qa-navitem"
                                :aria-current="tab === '{{ $group['id'] }}' ? 'location' : false"
                            >
                                <span class="qa-navitem__icon" aria-hidden="true">
                                    <x-ui.icon :name="$group['icon'] ?? 'diagnose'" class="h-4 w-4" />
                                </span>
                                <span class="qa-navitem__copy">
                                    <span class="qa-navitem__label">{{ $group['label'] }}</span>
                                    <span class="qa-navitem__meta">
                                        {{ count($group['commands']) }} teste{{ count($group['commands']) === 1 ? '' : 's' }}
                                        · {{ $module['status_label'] ?? 'Sem dados' }}
                                    </span>
                                </span>
                                <span class="qa-navitem__pip" aria-hidden="true"></span>
                            </a>
                            <ul class="qa-toc__tests">
                                @foreach ($group['commands'] as $item)
                                    <li>
                                        <a href="#{{ $cmdAnchor($item['id']) }}">{{ $item['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                </nav>
            </aside>

            <section id="qa-console" class="qa-console" x-ref="console">
                <div class="qa-console__bar">
                    <p>Temas de teste</p>
                    <div>
                        <button type="button" class="qa-console__tool" @click="expandAll()">Abrir todos</button>
                        <button type="button" class="qa-console__tool" @click="collapseAll()">Fechar todos</button>
                    </div>
                </div>
                @foreach ($groups as $group)
                    <details
                        id="qa-area-{{ $group['id'] }}"
                        class="qa-panel qa-pane"
                        data-tone="{{ $group['id'] }}"
                        :class="{ 'is-current': tab === '{{ $group['id'] }}' }"
                    >
                        <summary class="qa-panel__head qa-pane__summary">
                            <div class="qa-panel__title-wrap">
                                <span class="qa-panel__icon" aria-hidden="true">
                                    <x-ui.icon :name="$group['icon'] ?? 'diagnose'" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="qa-panel__eyebrow">{{ count($group['commands']) }} teste{{ count($group['commands']) === 1 ? '' : 's' }}</p>
                                    <h2 class="qa-panel__title">{{ $group['label'] }}</h2>
                                </div>
                            </div>
                            <span class="qa-pane__toggle" aria-hidden="true"></span>
                        </summary>
                        <p class="qa-pane__lead">{{ $group['description'] }}</p>
                        <div class="qa-panel__body">
                            <ul class="qa-commands">
                                @foreach ($group['commands'] as $item)
                                    @php $last = $lastByCommand[$item['id']] ?? null; @endphp
                                    <li id="{{ $cmdAnchor($item['id']) }}" class="qa-command">
                                        <span class="qa-command__icon" aria-hidden="true">
                                            <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                                        </span>
                                        <div class="qa-command__body">
                                            <div class="qa-command__title-row">
                                                <p class="qa-command__title">{{ $item['label'] }}</p>
                                                @if($last)
                                                    <span @class([
                                                        'qa-chip',
                                                        'qa-chip--ok' => $last->isPassed(),
                                                        'qa-chip--run' => $last->isRunning(),
                                                        'qa-chip--bad' => $last->isFailed(),
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
                    </details>
                @endforeach
            </section>

            <section id="qa-reports" class="qa-panel" aria-labelledby="qa-reports-title">
                <header class="qa-panel__head">
                    <div>
                        <p class="qa-panel__eyebrow">Auditoria</p>
                        <h2 id="qa-reports-title" class="qa-panel__title">Relatórios</h2>
                    </div>
                    <span class="qa-panel__meta">{{ $runs->count() }}</span>
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
                        </div>
                    @endforeach
                </div>
                <div class="qa-panel__body">
                    @if($runs->isEmpty())
                        <p class="qa-empty">Ainda não há execuções. Dispare um comando no console.</p>
                    @else
                        <ul class="qa-feed">
                            @foreach ($runs as $run)
                                <li>
                                    <a href="{{ route('admin.qa.show', $run) }}" class="qa-feed__item">
                                        <span @class([
                                            'qa-chip',
                                            'qa-chip--ok' => $run->isPassed(),
                                            'qa-chip--run' => $run->isRunning(),
                                            'qa-chip--bad' => $run->isFailed(),
                                        ])>{{ $run->statusLabel() }}</span>
                                        <span class="qa-feed__copy">
                                            <span class="qa-feed__title">{{ \App\Support\QaCatalog::label($run->command) }}</span>
                                            <span class="qa-feed__summary">{{ $run->summary }}</span>
                                        </span>
                                        <span class="qa-feed__when">
                                            {{ $run->durationLabel() }}
                                            <small>{{ $run->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}</small>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        </div>
    </div>
@endsection
