@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Sistema · Testes</p>
            <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $command['label'] ?? $run->command }}</h1>
            <p class="mt-1 max-w-2xl text-mist">{{ $run->summary }}</p>
        </div>
        <a href="{{ route('admin.qa.index') }}" class="text-sm text-mist transition hover:text-snow">← Comandos</a>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-3 text-sm">
        <span @class([
            'rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-wide',
            'bg-blue/20 text-blue-bright' => $run->isRunning(),
            'bg-brand/15 text-brand-bright' => $run->isPassed(),
            'bg-red-500/15 text-red-300' => ! $run->isRunning() && ! $run->isPassed(),
        ])>{{ $run->statusLabel() }}</span>
        <span class="text-mist">{{ $run->user?->name ?? '—' }}</span>
        <span class="text-mist">{{ $run->started_at?->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</span>
        @if($run->durationSeconds() !== null)
            <span class="text-mist">{{ $run->durationSeconds() }} s</span>
        @endif
        @if($run->isRunning())
            <span class="text-mist">A actualizar…</span>
        @endif
    </div>

    @if(! empty($run->meta))
        <section class="mb-6 rounded-sm border border-line bg-panel p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-mist">Métricas</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($run->meta as $key => $value)
                    @if(! is_array($value) && $value !== null && $value !== '')
                        <div>
                            <dt class="text-xs text-mist">{{ str_replace('_', ' ', (string) $key) }}</dt>
                            <dd class="mt-1 font-semibold text-snow">{{ is_bool($value) ? ($value ? 'sim' : 'não') : $value }}</dd>
                        </div>
                    @endif
                @endforeach
            </dl>
        </section>
    @endif

    @if(! empty($run->meta['checks']) && is_array($run->meta['checks']))
        <section class="mb-6 rounded-sm border border-line bg-panel p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-mist">Checagens</h2>
            <ul class="mt-3 divide-y divide-line">
                @foreach ($run->meta['checks'] as $check)
                    @php
                        $checkOk = (bool) ($check['ok'] ?? false);
                    @endphp
                    <li class="flex flex-wrap items-baseline justify-between gap-2 py-2 text-sm">
                        <span class="{{ $checkOk ? 'text-snow' : 'text-red-300' }}">{{ $check['name'] ?? '—' }}</span>
                        <span class="text-mist">{{ $checkOk ? 'OK' : 'Falhou' }}@if(! empty($check['detail'])) · {{ $check['detail'] }}@endif</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="rounded-sm border border-line bg-panel p-5">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-mist">Saída</h2>
        <pre class="mt-3 max-h-[28rem] overflow-auto whitespace-pre-wrap break-all font-mono text-xs leading-relaxed text-snow">{{ $run->output ?: 'Ainda sem saída.' }}</pre>
    </section>

    @if($run->isRunning())
        <script>setTimeout(function () { window.location.reload(); }, 3000);</script>
    @endif
@endsection
