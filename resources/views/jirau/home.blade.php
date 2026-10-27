@extends('layouts.jirau')

@section('content')
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
                @if($isAdmin && $jiraus->count() > 1)
                    Arraste pelo ícone para reordenar.
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

    <div class="jirau-home__grid">
        @forelse($jiraus as $board)
            @php
                $template = $board->template;
                $open = (int) $board->open_cards_count;
                $inbox = (int) $board->inbox_cards_count;
            @endphp
            <article
                class="jirau-home__card {{ $board->is_active ? '' : 'is-inactive' }}"
                data-jirau-home-card
                data-board-id="{{ $board->id }}"
                style="--jirau-col: {{ $template?->color() ?? '#2f6bff' }}"
            >
                <header class="jirau-home__card-head">
                    <span class="jirau-home__tools">
                        <span class="jirau-home__mark" aria-hidden="true">
                            <x-ui.icon :name="$template?->icon() ?? 'jirau'" class="h-4 w-4" />
                        </span>
                        <span class="jirau-home__count" title="Cartões abertos">{{ $open }}</span>
                    </span>
                    @if($isAdmin)
                        <button type="button" class="jirau-home__grip" data-jirau-home-handle aria-label="Reordenar {{ $board->name }}" title="Arrastar para reordenar">
                            <x-ui.icon name="grip" class="h-4 w-4" />
                        </button>
                    @endif
                </header>
                <p class="jirau-home__kicker">{{ $board->project?->name ?? 'Projeto' }}</p>
                <h2>{{ $board->name }}</h2>
                <div class="jirau-home__chips">
                    @if($template)
                        <span class="jirau-chip">{{ $template->label() }}</span>
                    @endif
                    @unless($board->is_active)
                        <span class="jirau-chip jirau-chip--warn">Inativo</span>
                    @endunless
                </div>
                <p class="jirau-home__meta">
                    @if($inbox > 0)
                        {{ $inbox }} na triagem
                    @else
                        Sem pedidos na triagem
                    @endif
                </p>
                <a href="{{ route('jirau.show', $board) }}" class="jirau-home__open">
                    <x-ui.icon name="jirau" class="h-4 w-4" />
                    <span>Abrir quadro</span>
                </a>
            </article>
        @empty
            <div class="jirau-home__empty">
                @if($isAdmin)
                    <p>Nenhum Jirau ainda. Escolha um projeto existente e crie o quadro aqui.</p>
                    <x-jirau.action tone="board" icon="plus" data-jirau-toggle="new-board">Adicionar quadro</x-jirau.action>
                @else
                    <p>Você ainda não foi convidado para um Jirau.</p>
                @endif
            </div>
        @endforelse
    </div>
</div>
@endsection
