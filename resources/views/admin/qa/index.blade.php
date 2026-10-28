@extends('layouts.admin')

@section('content')
    <div class="admin-hero">
        <div>
            <p class="admin-hero__kicker">Sistema</p>
            <h1 class="admin-hero__title">Testes</h1>
            <p class="admin-hero__lead">
                Comandos para a suíte PHPUnit, probes do bot Telegram e GET nas telas públicas. Cada execução gera um relatório.
            </p>
        </div>
        <div class="admin-hero__date">
            <strong>{{ $runs->count() }}</strong>
            <span>relatórios recentes</span>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        @foreach ($groups as $group)
            <section class="rounded-sm border border-line bg-panel p-5 sm:p-6">
                <h2 class="font-display text-lg font-semibold text-snow">{{ $group['label'] }}</h2>
                <p class="mt-1 text-sm text-mist">{{ $group['description'] }}</p>

                <ul class="mt-5 space-y-3">
                    @foreach ($group['commands'] as $item)
                        <li class="rounded-sm border border-line bg-ink/20 p-4">
                            <div class="flex items-start gap-3">
                                <span class="mt-0.5 text-brand-bright" aria-hidden="true">
                                    <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-snow">{{ $item['label'] }}</p>
                                    <p class="mt-1 text-xs text-mist">{{ $item['hint'] }}</p>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.qa.store') }}"
                                        class="mt-3"
                                        @if(! empty($item['confirm'])) data-confirm="{{ $item['confirm'] }}" @endif
                                    >
                                        @csrf
                                        <input type="hidden" name="command" value="{{ $item['id'] }}">
                                        @if($item['id'] === 'suite.phpunit' && $suiteRunning)
                                            <x-ui.button type="submit" variant="secondary" disabled>
                                                A correr…
                                            </x-ui.button>
                                        @else
                                            <x-ui.button type="submit" variant="secondary">
                                                Executar
                                            </x-ui.button>
                                        @endif
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    <section class="mt-10" aria-labelledby="qa-reports-title">
        <h2 id="qa-reports-title" class="font-display text-xl font-semibold text-snow">Relatórios</h2>
        <p class="mt-1 text-sm text-mist">Últimas 40 execuções. Abra um relatório para ver a saída completa.</p>

        @if($runs->isEmpty())
            <p class="mt-6 rounded-sm border border-line bg-panel px-4 py-6 text-sm text-mist">Ainda não há execuções. Dispare um comando acima.</p>
        @else
            <div class="mt-5 overflow-x-auto rounded-sm border border-line">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-ink/40 text-xs uppercase tracking-wide text-mist">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3 font-semibold">Comando</th>
                            <th class="px-4 py-3 font-semibold">Resumo</th>
                            <th class="px-4 py-3 font-semibold">Quem</th>
                            <th class="px-4 py-3 font-semibold">Duração</th>
                            <th class="px-4 py-3 font-semibold">Quando</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($runs as $run)
                            <tr class="border-t border-line bg-panel">
                                <td class="px-4 py-3">
                                    <span @class([
                                        'rounded-sm px-2 py-1 text-[10px] font-semibold uppercase tracking-wide',
                                        'bg-blue/20 text-blue-bright' => $run->isRunning(),
                                        'bg-brand/15 text-brand-bright' => $run->isPassed(),
                                        'bg-red-500/15 text-red-300' => ! $run->isRunning() && ! $run->isPassed(),
                                    ])>{{ $run->statusLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-snow">
                                    <a href="{{ route('admin.qa.show', $run) }}" class="font-semibold hover:text-brand-bright">{{ \App\Support\QaCatalog::label($run->command) }}</a>
                                </td>
                                <td class="max-w-sm truncate px-4 py-3 text-mist" title="{{ $run->summary }}">{{ $run->summary }}</td>
                                <td class="px-4 py-3 text-mist">{{ $run->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-mist">{{ $run->durationSeconds() === null ? '—' : $run->durationSeconds().' s' }}</td>
                                <td class="px-4 py-3 text-mist">{{ $run->created_at?->timezone(config('app.timezone'))->format('d/m H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
