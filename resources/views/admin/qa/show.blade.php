@extends('layouts.admin')

@section('content')
    @php
        $metrics = [];
        $labels = [
            'tests' => 'Testes',
            'assertions' => 'Asserções',
            'failures' => 'Falhas',
            'errors' => 'Erros',
            'exit_code' => 'Exit',
            'ok_count' => 'OK',
            'total' => 'Total',
            'pending' => 'Pendentes',
            'ran' => 'Corridas',
            'files' => 'Ficheiros',
            'level' => 'Nível',
            'failed' => 'Falhas',
            'status' => 'HTTP',
            'bytes' => 'Bytes',
            'php' => 'PHP',
        ];
        foreach ((array) $run->meta as $key => $value) {
            if (is_array($value) || $value === null || $value === '') {
                continue;
            }
            $metrics[] = [
                'label' => $labels[$key] ?? str_replace('_', ' ', (string) $key),
                'value' => is_bool($value) ? ($value ? 'sim' : 'não') : $value,
            ];
        }
        $checks = is_array($run->meta['checks'] ?? null) ? $run->meta['checks'] : [];
        $checkOk = collect($checks)->where('ok', true)->count();
        $checkTotal = count($checks);
    @endphp

    <div class="qa-lab qa-lab--report">
        <div class="admin-hero">
            <div>
                <p class="admin-hero__kicker">Sistema · Testes</p>
                <h1 class="admin-hero__title">{{ $command['label'] ?? $run->command }}</h1>
                <p class="admin-hero__lead">{{ $run->summary }}</p>
            </div>
            <div class="qa-lab__hero-side">
                <div @class([
                    'admin-hero__date qa-lab__health',
                    'qa-lab__health--ok' => $run->isPassed(),
                    'qa-lab__health--run' => $run->isRunning(),
                    'qa-lab__health--warn' => $run->isFailed(),
                ])>
                    <strong>{{ $run->statusLabel() }}</strong>
                    <span>{{ $run->durationLabel() }}</span>
                </div>
                <a href="{{ route('admin.qa.index') }}" class="qa-lab__back">← Comandos</a>
            </div>
        </div>

        <div class="qa-report__meta">
            <span>{{ $run->user?->name ?? '—' }}</span>
            <span>{{ $run->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
            @if($run->isRunning())
                <span class="qa-report__live">A actualizar…</span>
            @endif
        </div>

        @if($metrics !== [])
            <div class="dash-stats qa-report__kpis" aria-label="Métricas da execução">
                @foreach ($metrics as $metric)
                    <article class="dash-stat dash-stat--projects">
                        <span class="dash-stat__label">{{ $metric['label'] }}</span>
                        <span class="dash-stat__value-row">
                            <span class="dash-stat__value">{{ $metric['value'] }}</span>
                        </span>
                    </article>
                @endforeach
            </div>
        @endif

        @if($checks !== [])
            <section class="dash-panel">
                <header class="dash-panel__head">
                    <div class="dash-panel__title-wrap">
                        <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="qualified" class="h-5 w-5" /></span>
                        <div>
                            <p class="dash-panel__eyebrow">Verificação</p>
                            <h2 class="dash-panel__title">Checagens</h2>
                        </div>
                    </div>
                    <p class="dash-panel__meta">{{ $checkOk }}/{{ $checkTotal }} OK</p>
                </header>
                <ul class="qa-checks">
                    @foreach ($checks as $check)
                        @php $ok = (bool) ($check['ok'] ?? false); @endphp
                        <li class="qa-check {{ $ok ? 'is-ok' : 'is-bad' }}">
                            <span class="qa-check__dot" aria-hidden="true"></span>
                            <span class="qa-check__name">{{ $check['name'] ?? '—' }}</span>
                            <span class="qa-check__detail">{{ $ok ? 'OK' : 'Falhou' }}@if(! empty($check['detail'])) · {{ $check['detail'] }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="dash-panel qa-terminal">
            <header class="dash-panel__head">
                <div class="dash-panel__title-wrap">
                    <span class="dash-panel__icon" aria-hidden="true"><x-ui.icon name="code" class="h-5 w-5" /></span>
                    <div>
                        <p class="dash-panel__eyebrow">Console</p>
                        <h2 class="dash-panel__title">Saída</h2>
                    </div>
                </div>
            </header>
            <pre class="qa-terminal__body">{{ $run->output ?: 'Ainda sem saída.' }}</pre>
        </section>
    </div>

    @if($run->isRunning())
        <script>setTimeout(function () { window.location.reload(); }, 3000);</script>
    @endif
@endsection
