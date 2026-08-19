@extends('layouts.admin')

@section('content')
<div class="pm-workspace">
    <div class="pm-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Site institucional</p>
            <h1 class="pm-workspace__title">Portfólio na home</h1>
            <p class="pm-workspace__lead">
                Arraste os cards entre colunas para publicar, esconder ou marcar com estrela.
                A ordem na coluna é a ordem na página inicial.
            </p>
        </div>
        <div class="pm-workspace__actions">
            <a href="{{ route('home') }}#projetos" target="_blank" rel="noopener" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="external" class="h-4 w-4" />
                Ver home
            </a>
            <a href="{{ route('admin.projects.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="project" class="h-4 w-4" />
                Fichas de projeto
            </a>
        </div>
    </div>

    <div
        class="home-board"
        data-home-project-board
        data-save-url="{{ $saveUrl }}"
    >
        @foreach ([
            ['lane' => 'featured', 'title' => 'Destaques (estrela)', 'hint' => 'Bloco próprio no topo do portfólio', 'tone' => 'star', 'items' => $featured],
            ['lane' => 'portfolio', 'title' => 'Portfólio', 'hint' => 'Grelha abaixo dos destaques', 'tone' => 'site', 'items' => $portfolio],
            ['lane' => 'hidden', 'title' => 'Fora da home', 'hint' => 'Não aparecem no site', 'tone' => 'hidden', 'items' => $hidden],
        ] as $column)
            <section class="home-board__column home-board__column--{{ $column['tone'] }}" data-home-lane="{{ $column['lane'] }}">
                <header class="home-board__header">
                    <div>
                        <h2>
                            @if($column['lane'] === 'featured')
                                <x-ui.icon name="star" class="h-4 w-4" />
                            @endif
                            {{ $column['title'] }}
                        </h2>
                        <p>{{ $column['hint'] }}</p>
                    </div>
                    <span class="pm-board__count" data-column-count>{{ $column['items']->count() }}</span>
                </header>
                <div class="home-board__list" data-column-list>
                    @forelse($column['items'] as $project)
                        @include('admin.home-projects.partials.card', ['project' => $project, 'lane' => $column['lane']])
                    @empty
                        <p class="pm-board__empty" data-empty>Solte projetos aqui.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    <p class="mt-4 text-xs text-mist" data-home-board-status>Alterações gravam-se ao soltar o card.</p>
</div>
@endsection
