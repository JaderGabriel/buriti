@extends('layouts.app')

@section('title', 'Admin — BURI-TI')

@section('body')
@php
    /**
     * Fluxo: visão → comercial (entrada) → entrega → sistema.
     * Mensagem do site → Contato CRM → Oportunidade → Projeto → Tarefa.
     */
    $navGroups = [
        [
            'label' => null,
            'items' => [
                [
                    'route' => 'admin.dashboard',
                    'label' => 'Início',
                    'pattern' => 'admin.dashboard',
                    'icon' => 'dashboard',
                ],
            ],
        ],
        [
            'label' => 'Comercial',
            'items' => [
                [
                    'route' => 'admin.messages.index',
                    'label' => 'Mensagens',
                    'pattern' => 'admin.messages.*',
                    'icon' => 'message',
                    'badge' => (int) ($adminUnreadMessages ?? 0),
                ],
                [
                    'route' => 'admin.companies.index',
                    'label' => 'Empresas',
                    'pattern' => 'admin.companies.*',
                    'icon' => 'company',
                ],
                [
                    'route' => 'admin.contacts.index',
                    'label' => 'Contatos',
                    'pattern' => 'admin.contacts.*',
                    'icon' => 'contact',
                ],
                [
                    'route' => 'admin.opportunities.index',
                    'label' => 'Oportunidades',
                    'pattern' => 'admin.opportunities.*',
                    'icon' => 'opportunity',
                ],
            ],
        ],
        [
            'label' => 'Entrega',
            'items' => [
                [
                    'route' => 'admin.projects.index',
                    'label' => 'Projetos',
                    'pattern' => ['admin.projects.index', 'admin.projects.create', 'admin.projects.store', 'admin.projects.edit', 'admin.projects.update'],
                    'icon' => 'project',
                ],
                [
                    'route' => 'admin.home-projects.edit',
                    'label' => 'Site',
                    'pattern' => 'admin.home-projects.*',
                    'icon' => 'star',
                ],
                [
                    'route' => 'admin.tasks.index',
                    'label' => 'Agenda',
                    'pattern' => 'admin.tasks.*',
                    'icon' => 'task',
                ],
            ],
        ],
        [
            'label' => 'Jirau',
            'class' => 'admin-nav__group--jirau',
            'items' => [
                [
                    'route' => 'jirau.home',
                    'label' => 'Quadros',
                    'pattern' => ['jirau.home', 'jirau.show', 'jirau.cards.*', 'jirau.demands.*', 'jirau.members.*', 'jirau.steps.*', 'jirau.status'],
                    'icon' => 'jirau',
                ],
                [
                    'route' => 'jirau.overview',
                    'label' => 'Quadro geral',
                    'pattern' => 'jirau.overview',
                    'icon' => 'flow',
                ],
                [
                    'route' => 'jirau.analytics',
                    'label' => 'Analítica',
                    'pattern' => ['jirau.analytics', 'jirau.analytics.show'],
                    'icon' => 'bi',
                ],
                [
                    'route' => 'jirau.staff',
                    'label' => 'Equipe',
                    'pattern' => ['jirau.staff', 'jirau.staff.store'],
                    'icon' => 'users',
                ],
            ],
        ],
        [
            'label' => 'Sistema',
            'items' => array_values(array_filter([
                auth()->user()->is_admin
                    ? [
                        'route' => 'admin.users.index',
                        'label' => 'Usuários',
                        'pattern' => 'admin.users.*',
                        'icon' => 'users',
                    ]
                    : null,
                [
                    'route' => 'admin.integrations.edit',
                    'label' => 'Integrações',
                    'pattern' => 'admin.integrations.*',
                    'icon' => 'integrations',
                ],
                [
                    'route' => 'admin.settings.edit',
                    'label' => 'Configurações',
                    'pattern' => 'admin.settings.*',
                    'icon' => 'settings',
                ],
            ])),
        ],
    ];
    $adminUser = auth()->user();
    $adminFirstName = explode(' ', trim($adminUser->name))[0] ?? 'olá';
    $runtimeVersions = $runtimeVersions ?? \App\Support\RuntimeVersions::labels();
@endphp

<div class="admin-shell" data-admin-shell>
    <div class="admin-orbits" aria-hidden="true">
        <span class="admin-orbit admin-orbit--a"></span>
        <span class="admin-orbit admin-orbit--b"></span>
        <span class="admin-orbit admin-orbit--c"></span>
    </div>

    <div class="admin-overlay hidden" data-admin-overlay hidden></div>

    <aside
        class="admin-sidebar"
        data-admin-sidebar
        data-open="false"
    >
        <div class="admin-brand">
            <a href="{{ route('admin.dashboard') }}" class="admin-brand__link" data-admin-close>
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="admin-brand__logo">
                <span class="admin-brand__text">
                    <span class="admin-brand__name">BURI-TI</span>
                    <span class="admin-brand__tag">Painel admin</span>
                </span>
            </a>
            <button type="button" class="admin-icon-btn admin-icon-btn--ghost admin-mobile-only" data-admin-close aria-label="Fechar menu">✕</button>
        </div>

        @include('admin.partials.shortcuts')

        <form method="GET" action="{{ route('admin.search') }}" class="admin-search admin-search--sidebar">
            <label class="sr-only" for="admin-search">Buscar no CRM</label>
            <span class="admin-search__icon" aria-hidden="true">
                <x-ui.icon name="search" class="h-4 w-4" />
            </span>
            <input
                id="admin-search"
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="Buscar contato, empresa…"
                class="admin-search__input"
            >
        </form>

        <nav class="admin-nav" aria-label="Menu do painel">
            @foreach($navGroups as $group)
                <div @class(['admin-nav__group', $group['class'] ?? null])>
                    @if($group['label'])
                        <p class="admin-nav__label">{{ $group['label'] }}</p>
                    @endif
                    @foreach($group['items'] as $item)
                        @php $active = is_array($item['pattern']) ? request()->routeIs(...$item['pattern']) : request()->routeIs($item['pattern']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            data-admin-close
                            @class(['admin-nav__link', 'is-active' => $active])
                        >
                            <span class="admin-nav__icon">
                                <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                            </span>
                            <span class="admin-nav__text">{{ $item['label'] }}</span>
                            @if(! empty($item['badge']))
                                <span class="admin-nav__badge">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
            <p class="admin-versions" aria-label="Versões do ambiente">
                <span>Laravel {{ $runtimeVersions['laravel'] }}</span>
                <span>PHP {{ $runtimeVersions['php'] }}</span>
                <span>Server {{ $runtimeVersions['server'] }}</span>
                <span>{{ $runtimeVersions['release'] }}</span>
            </p>
        </nav>

        <a href="{{ route('admin.profile.edit') }}" class="admin-user" data-admin-close>
            @if($adminUser->avatarUrl())
                <img src="{{ $adminUser->avatarUrl() }}" alt="" class="admin-user__avatar">
            @else
                <span class="admin-user__avatar admin-user__avatar--fallback">{{ $adminUser->initials() }}</span>
            @endif
            <span class="admin-user__meta">
                <span class="admin-user__name">{{ $adminUser->name }}</span>
                <span class="admin-user__handle">{{ '@'.$adminUser->username }}</span>
            </span>
        </a>
    </aside>

    <div class="admin-stage">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button type="button" class="admin-icon-btn admin-mobile-only" data-admin-open aria-label="Abrir menu">
                    <span class="admin-menu-lines" aria-hidden="true"></span>
                </button>
                <p class="admin-topbar__title admin-mobile-only">Admin BURI-TI</p>
                <form method="GET" action="{{ route('admin.search') }}" class="admin-search admin-search--top">
                    <label class="sr-only" for="admin-search-top">Buscar no CRM</label>
                    <span class="admin-search__icon" aria-hidden="true">
                        <x-ui.icon name="search" class="h-4 w-4" />
                    </span>
                    <input
                        id="admin-search-top"
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Buscar qualquer coisa…"
                        class="admin-search__input"
                    >
                </form>
            </div>

            <div class="admin-topbar__right">
                <div class="admin-mobile-only">
                    @include('admin.partials.shortcuts', ['compact' => true])
                </div>
                <a href="{{ route('admin.profile.edit') }}" class="admin-user admin-user--chip" title="Perfil">
                    @if($adminUser->avatarUrl())
                        <img src="{{ $adminUser->avatarUrl() }}" alt="" class="admin-user__avatar">
                    @else
                        <span class="admin-user__avatar admin-user__avatar--fallback">{{ $adminUser->initials() }}</span>
                    @endif
                    <span class="admin-user__meta">
                        <span class="admin-user__name">{{ $adminFirstName }}</span>
                        <span class="admin-user__handle">Perfil</span>
                    </span>
                </a>
            </div>
        </header>

        <div class="admin-content">
            <x-ui.flash />
            @yield('content')
        </div>
    </div>

    <button
        type="button"
        class="back-to-top"
        data-back-to-top
        aria-label="Voltar ao topo"
        title="Voltar ao topo"
        hidden
    >
        <x-ui.icon name="arrow-up" class="h-5 w-5" />
    </button>
</div>
@endsection
