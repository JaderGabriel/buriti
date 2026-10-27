@extends('layouts.jirau')

@section('content')
<div class="jirau-workspace">
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Jirau</p>
            <h1 class="pm-workspace__title">Quadro geral</h1>
            <p class="pm-workspace__lead">
                Leitura unificada dos cartões nos Jiraus — não se cria trabalho aqui.
                Cada cartão abre no quadro de origem. {{ $boardCount }} quadro{{ $boardCount === 1 ? '' : 's' }} no filtro.
            </p>
        </div>
        <div class="jirau-workspace__actions">
            <a href="{{ route('jirau.staff') }}" class="pm-btn pm-btn--ghost">Equipe</a>
            <a href="{{ route('jirau.home') }}" class="pm-btn pm-btn--ghost">Quadros</a>
        </div>
    </div>

    <form method="GET" action="{{ route('jirau.overview') }}" class="jirau-filters">
        <label class="block text-sm">
            <span class="text-mist">Estado dos quadros</span>
            <select name="estado" class="jirau-input" onchange="this.form.submit()">
                <option value="ativos" @selected($estado === 'ativos')>Ativos</option>
                <option value="inativos" @selected($estado === 'inativos')>Inativos</option>
                <option value="todos" @selected($estado === 'todos')>Todos</option>
            </select>
        </label>
    </form>

    <div class="jirau-board jirau-board--overview">
        @foreach($laneDefs as $lane)
            @php $cards = $lanes[$lane->value] ?? collect(); @endphp
            <section class="jirau-column" style="--jirau-col: {{ $lane->color() }}">
                <header class="jirau-column__head">
                    <div>
                        <h2>{{ $lane->label() }}</h2>
                        <p>{{ $lane->hint() }}</p>
                    </div>
                    <span class="jirau-column__count">{{ $cards->count() }}</span>
                </header>
                <div class="jirau-column__list">
                    @forelse($cards as $card)
                        @include('jirau.partials.card', [
                            'card' => $card,
                            'jirau' => $card->jirau,
                            'canDrag' => false,
                            'showBoard' => true,
                        ])
                    @empty
                        <p class="jirau-column__empty">Nenhum cartão nesta faixa.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>
</div>
@endsection
