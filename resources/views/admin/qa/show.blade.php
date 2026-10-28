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
        $healthTone = $run->isPassed() ? 'ok' : ($run->isRunning() ? 'run' : 'warn');
        $healthIcon = match ($healthTone) {
            'ok' => 'qualified',
            'run' => 'play',
            default => 'lost',
        };
        $canRepeat = ! empty($command['id'] ?? $run->command);
        $confirm = $command['confirm'] ?? null;
        $repeatBlocked = ($command['kind'] ?? '') === 'suite' && $run->isRunning();
        $groupId = \App\Support\QaCatalog::groupId((string) $run->command);
        $consoleHash = $groupId ? '#qa-area-'.$groupId : '#qa-console';
    @endphp

    <div class="qa-lab qa-lab--report">
        <header class="qa-toolbar">
            <div class="qa-toolbar__copy">
                <p class="admin-hero__kicker">Sistema · Testes</p>
                <h1 class="qa-toolbar__title">{{ $command['label'] ?? $run->command }}</h1>
                <p class="qa-toolbar__lead">{{ $run->summary }}</p>
            </div>

            <nav class="qa-flow" aria-label="Fluxo do laboratório">
                <a class="qa-flow__step is-done" href="{{ route('admin.qa.index') }}#qa-kpis"><span>1</span> Saúde</a>
                <a class="qa-flow__step is-done" href="{{ route('admin.qa.index') }}{{ $consoleHash }}"><span>2</span> Executar</a>
                <a class="qa-flow__step is-current" href="#qa-output"><span>3</span> Relatório</a>
            </nav>

            <div class="qa-toolbar__actions">
                <div class="qa-health qa-health--{{ $healthTone }}">
                    <span class="qa-health__icon" aria-hidden="true">
                        <x-ui.icon :name="$healthIcon" class="h-4 w-4" />
                    </span>
                    <strong>{{ $run->statusLabel() }}</strong>
                    <span>{{ $run->durationLabel() }}</span>
                </div>
                @if($canRepeat)
                    <form method="POST" action="{{ route('admin.qa.store') }}" @if($confirm) data-confirm="{{ $confirm }}" @endif>
                        @csrf
                        <input type="hidden" name="command" value="{{ $command['id'] ?? $run->command }}">
                        <button type="submit" class="qa-lab__run" @disabled($repeatBlocked)>
                            <x-ui.icon name="play" class="h-4 w-4" />
                            {{ $repeatBlocked ? 'A correr…' : 'Repetir' }}
                        </button>
                    </form>
                @endif
                <a href="{{ route('admin.qa.index') }}{{ $consoleHash }}" class="qa-lab__ghost">← Comandos</a>
            </div>
        </header>

        <p class="qa-report__meta">
            <span>{{ $run->user?->name ?? '—' }}</span>
            <span>{{ $run->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
            @if($run->isRunning())
                <span class="qa-report__live">A actualizar…</span>
            @endif
        </p>

        <div class="qa-report">
            <div class="qa-report__facts">
                @if($metrics !== [])
                    <ul id="qa-metrics" class="qa-metrics" aria-label="Métricas da execução">
                        @foreach ($metrics as $metric)
                            <li>
                                <span>{{ $metric['label'] }}</span>
                                <strong>{{ $metric['value'] }}</strong>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($checks !== [])
                    <section id="qa-checks" class="qa-panel">
                        <header class="qa-panel__head">
                            <div>
                                <p class="qa-panel__eyebrow">Verificação</p>
                                <h2 class="qa-panel__title">Checagens</h2>
                            </div>
                            <span class="qa-panel__meta">{{ $checkOk }}/{{ $checkTotal }} OK</span>
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
            </div>

            <section id="qa-output" class="qa-panel qa-terminal">
                <header class="qa-panel__head">
                    <div>
                        <p class="qa-panel__eyebrow">Console</p>
                        <h2 class="qa-panel__title">Saída</h2>
                    </div>
                </header>
                <pre class="qa-terminal__body">{{ $run->output ?: 'Ainda sem saída.' }}</pre>
            </section>
        </div>
    </div>

    @if($run->isRunning())
        <script>setTimeout(function () { window.location.reload(); }, 3000);</script>
    @endif
@endsection
