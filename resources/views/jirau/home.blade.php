@extends('layouts.jirau')

@section('content')
@php
    $activeBoards = $activeBoards ?? $jiraus->where('is_active', true);
    $inactiveBoards = $inactiveBoards ?? $jiraus->where('is_active', false);
@endphp
<div
    class="jirau-home"
    data-jirau-home
    @if($isAdmin)
        data-can-drag="1"
        data-reorder-url="{{ route('jirau.boards.reorder') }}"
    @endif
>
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Jirau</p>
            <h1 class="pm-workspace__title">Quadros ágeis</h1>
            <p class="pm-workspace__lead">
                Cada projeto em andamento ganha um Jirau. Membros pedem demandas; o admin posiciona, estima e conduz o fluxo.
                @if($isAdmin && $activeBoards->count() > 1)
                    Arraste pelo ícone para reordenar os ativos.
                @endif
            </p>
        </div>
        <div class="jirau-workspace__actions">
            @if($isAdmin)
                <x-jirau.action tone="board" icon="plus" data-jirau-toggle="new-board">Novo quadro</x-jirau.action>
                <x-jirau.action tone="steps" icon="dashboard" :href="route('jirau.overview')">Quadro geral</x-jirau.action>
                <x-jirau.action tone="people" icon="users" :href="route('jirau.staff')">Equipe</x-jirau.action>
                <x-jirau.action tone="analytics" icon="bi" :href="route('jirau.analytics')">Analítica</x-jirau.action>
                <x-jirau.action tone="project" icon="project" :href="route('admin.projects.index')">Abrir projetos</x-jirau.action>
            @endif
        </div>
    </div>

    @if($jiraus->isEmpty())
        <div class="jirau-home__empty">
            @if($isAdmin)
                <p>Nenhum Jirau ainda. Escolha um projeto existente e crie o quadro aqui.</p>
                <x-jirau.action tone="board" icon="plus" data-jirau-toggle="new-board">Adicionar quadro</x-jirau.action>
            @else
                <p>Você ainda não foi convidado para um Jirau.</p>
            @endif
        </div>
    @else
        @if($activeBoards->isNotEmpty())
            <div class="jirau-home__grid" data-jirau-home-grid>
                @foreach($activeBoards as $board)
                    @include('jirau.partials.home-card', [
                        'board' => $board,
                        'draggable' => $isAdmin,
                        'canDelete' => $isAdmin && $board->isEmpty() && ! $board->isDemo(),
                    ])
                @endforeach
            </div>
        @elseif($inactiveBoards->isNotEmpty())
            <p class="jirau-home__none">Nenhum quadro ativo no momento.</p>
        @endif

        @if($inactiveBoards->isNotEmpty())
            <section class="jirau-home__idle" aria-labelledby="jirau-idle-title">
                <header class="jirau-home__idle-head">
                    <p class="jirau-home__kicker">Arquivo</p>
                    <h2 id="jirau-idle-title">Quadros inativos</h2>
                    <p>Ficam fora do quadro geral. Dá para reativar ou remover se estiverem vazios.</p>
                </header>
                <div class="jirau-home__grid jirau-home__grid--idle">
                    @foreach($inactiveBoards as $board)
                        @include('jirau.partials.home-card', [
                            'board' => $board,
                            'draggable' => false,
                            'canDelete' => $isAdmin && $board->isEmpty() && ! $board->isDemo(),
                        ])
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
