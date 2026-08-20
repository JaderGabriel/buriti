@extends('layouts.admin')

@section('content')
<div class="pm-workspace">
    <div class="pm-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Site institucional</p>
            <h1 class="pm-workspace__title">Portfólio no site</h1>
            <p class="pm-workspace__lead">
                Monte a página inicial em duas áreas: a <strong class="text-snow">pública</strong> (destaques e código aberto)
                e a <strong class="text-snow">restrita</strong> (projetos visíveis sem código). Arraste os cards; a ordem na coluna é a da página.
            </p>
        </div>
        <div class="pm-workspace__actions">
            <div class="pm-density" data-home-density>
                <button type="button" class="pm-chip" data-home-minimize-all title="Só visual — não altera dados">Minimizar todos</button>
                <button type="button" class="pm-chip" data-home-expand-all title="Só visual — não altera dados">Expandir todos</button>
            </div>
            <a href="{{ route('home') }}#projetos" target="_blank" rel="noopener" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="external" class="h-4 w-4" />
                Ver site
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
        <section class="home-board__region home-board__region--public" aria-labelledby="home-board-public">
            <header class="home-board__region-head">
                <div>
                    <p class="home-board__region-kicker">Parte pública</p>
                    <h2 id="home-board-public">Com links no site</h2>
                    <p>Faixa de destaques e grade de código aberto. Site e GitHub podem aparecer no card.</p>
                </div>
            </header>
            <div class="home-board__region-grid home-board__region-grid--pair">
                @include('admin.home-projects.partials.column', ['column' => [
                    'lane' => 'featured',
                    'title' => 'Destaques',
                    'hint' => 'Bloco próprio no topo do portfólio',
                    'tone' => 'star',
                    'items' => $featured,
                ]])
                @include('admin.home-projects.partials.column', ['column' => [
                    'lane' => 'portfolio',
                    'title' => 'Código aberto',
                    'hint' => 'Grade abaixo dos destaques',
                    'tone' => 'site',
                    'items' => $portfolio,
                ]])
            </div>
        </section>

        <section class="home-board__region home-board__region--restricted" aria-labelledby="home-board-restricted">
            <header class="home-board__region-head">
                <div>
                    <p class="home-board__region-kicker">Parte restrita</p>
                    <h2 id="home-board-restricted">No site, sem código</h2>
                    <p>Faixa de projetos confidenciais — stack e resultado visíveis, sem links de site ou GitHub.</p>
                </div>
                <span class="home-board__region-badge">Contrato / NDA</span>
            </header>
            <div class="home-board__region-grid">
                @include('admin.home-projects.partials.column', ['column' => [
                    'lane' => 'restricted',
                    'title' => 'Projetos confidenciais',
                    'hint' => 'Continua no site, com o selo de repositório privado',
                    'tone' => 'nda',
                    'items' => $restricted,
                ]])
            </div>
        </section>

        <section class="home-board__region home-board__region--off" aria-labelledby="home-board-off">
            <header class="home-board__region-head">
                <div>
                    <p class="home-board__region-kicker">Fora do site</p>
                    <h2 id="home-board-off">Só no painel</h2>
                    <p>Não entram na página inicial. Ficha, etapas e anexos ficam na operação interna.</p>
                </div>
            </header>
            <div class="home-board__region-grid">
                @include('admin.home-projects.partials.column', ['column' => [
                    'lane' => 'hidden',
                    'title' => 'Fora do site',
                    'hint' => 'O visitante não vê este projeto',
                    'tone' => 'hidden',
                    'items' => $hidden,
                ]])
            </div>
        </section>
    </div>

    <p class="mt-4 text-xs text-mist" data-home-board-status>As alterações são salvas ao soltar o card. Mover entre pública e restrita altera o tipo de repositório.</p>
</div>
@endsection
