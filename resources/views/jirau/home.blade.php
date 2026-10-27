@extends('layouts.jirau')

@section('content')
<div class="jirau-home">
    <div class="pm-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Jirau</p>
            <h1 class="pm-workspace__title">Quadros ágeis</h1>
            <p class="pm-workspace__lead">
                Cada projeto em andamento ganha um Jirau — plataforma de entrega no lugar do Trello/Notion.
                Membros pedem demandas; o admin posiciona, estima e conduz o fluxo.
            </p>
        </div>
        <div class="jirau-workspace__actions">
            @if($isAdmin)
                <a href="{{ route('jirau.overview') }}" class="pm-btn pm-btn--ghost">Quadro geral</a>
                <a href="{{ route('jirau.staff') }}" class="pm-btn pm-btn--ghost">Equipe</a>
                <a href="{{ route('jirau.analytics') }}" class="pm-btn pm-btn--ghost">Analítica</a>
                <a href="{{ route('admin.projects.index') }}" class="pm-btn pm-btn--ghost">Abrir projetos</a>
            @endif
        </div>
    </div>

    <div class="jirau-home__grid">
        @forelse($jiraus as $board)
            <a href="{{ route('jirau.show', $board) }}" class="jirau-home__card">
                <p class="jirau-home__kicker">{{ $board->project?->name ?? 'Projeto' }}</p>
                <h2>{{ $board->name }}</h2>
                <p>
                    {{ $board->open_cards_count }} cartão{{ $board->open_cards_count === 1 ? '' : 's' }} em aberto
                    @unless($board->is_active)
                        · inativo
                    @endunless
                </p>
            </a>
        @empty
            <div class="jirau-home__empty">
                @if($isAdmin)
                    <p>Nenhum Jirau ainda. Abra um projeto em andamento e crie o quadro.</p>
                    <a href="{{ route('admin.projects.index') }}" class="pm-btn pm-btn--primary">Ir aos projetos</a>
                @else
                    <p>Você ainda não foi convidado para um Jirau.</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
@endsection
