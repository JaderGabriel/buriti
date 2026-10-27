@extends('layouts.app')

@section('title', ($jirau?->name ?? 'Jirau').' — BURI-TI')

@section('body')
@php
    $jirauUser = auth()->user();
    $jirauFirstName = explode(' ', trim($jirauUser->name))[0] ?? 'olá';
    $navJiraus = ($jirauUser->is_admin || $jirauUser->jirau_role)
        ? \App\Models\Jirau::query()->with('project')->orderBy('name')->get()
        : $jirauUser->jiraus()->with('project')->orderBy('name')->get();
    $currentJirau = $jirau ?? null;
@endphp

<div class="admin-shell" data-admin-shell data-jirau-shell>
    <div class="admin-orbits" aria-hidden="true">
        <span class="admin-orbit admin-orbit--a"></span>
        <span class="admin-orbit admin-orbit--b"></span>
        <span class="admin-orbit admin-orbit--c"></span>
    </div>

    <div class="admin-overlay hidden" data-admin-overlay hidden></div>

    <aside class="admin-sidebar" data-admin-sidebar data-open="false">
        <div class="admin-brand">
            <a href="{{ route('jirau.home') }}" class="admin-brand__link" data-admin-close>
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="admin-brand__logo">
                <span class="admin-brand__text">
                    <span class="admin-brand__name">Jirau</span>
                    <span class="admin-brand__tag">Quadro ágil BURI-TI</span>
                </span>
            </a>
            <button type="button" class="admin-icon-btn admin-icon-btn--ghost admin-mobile-only" data-admin-close aria-label="Fechar menu">✕</button>
        </div>

        <div class="admin-shortcuts">
            @if($jirauUser->is_admin)
                <a href="{{ route('admin.dashboard') }}" class="admin-icon-btn" title="Painel admin" aria-label="Painel admin">
                    <x-ui.icon name="dashboard" class="h-4 w-4" />
                </a>
            @endif
            <button
                type="button"
                data-theme-toggle
                class="admin-icon-btn admin-icon-btn--theme"
                aria-label="Ativar modo escuro"
                aria-pressed="false"
                title="Alternar tema"
            >
                <x-ui.icon name="sun" class="hidden h-4 w-4 dark:block" />
                <x-ui.icon name="moon" class="block h-4 w-4 dark:hidden" />
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="admin-icon-btn admin-icon-btn--logout" title="Sair" aria-label="Sair">
                    <x-ui.icon name="logout" class="h-4 w-4" />
                </button>
            </form>
        </div>

        <nav class="admin-nav" aria-label="Jiraus">
            @if($jirauUser->is_admin)
                <div class="admin-nav__group admin-nav__group--jirau">
                    <p class="admin-nav__label">Jirau</p>
                    <a
                        href="{{ route('jirau.home') }}"
                        data-admin-close
                        @class(['admin-nav__link', 'is-active' => request()->routeIs('jirau.home')])
                    >
                        <span class="admin-nav__icon">
                            <x-ui.icon name="jirau" class="h-4 w-4" />
                        </span>
                        <span class="admin-nav__text">Quadros</span>
                    </a>
                    <a
                        href="{{ route('jirau.overview') }}"
                        data-admin-close
                        @class(['admin-nav__link', 'is-active' => request()->routeIs('jirau.overview')])
                    >
                        <span class="admin-nav__icon">
                            <x-ui.icon name="flow" class="h-4 w-4" />
                        </span>
                        <span class="admin-nav__text">Quadro geral</span>
                    </a>
                    <a
                        href="{{ route('jirau.analytics') }}"
                        data-admin-close
                        @class(['admin-nav__link', 'is-active' => request()->routeIs('jirau.analytics', 'jirau.analytics.show')])
                    >
                        <span class="admin-nav__icon">
                            <x-ui.icon name="bi" class="h-4 w-4" />
                        </span>
                        <span class="admin-nav__text">Analítica</span>
                    </a>
                    <a
                        href="{{ route('jirau.staff') }}"
                        data-admin-close
                        @class(['admin-nav__link', 'is-active' => request()->routeIs('jirau.staff', 'jirau.staff.store')])
                    >
                        <span class="admin-nav__icon">
                            <x-ui.icon name="users" class="h-4 w-4" />
                        </span>
                        <span class="admin-nav__text">Equipe</span>
                    </a>
                </div>
            @endif
            <div class="admin-nav__group">
                <p class="admin-nav__label">Quadros</p>
                @forelse($navJiraus as $navBoard)
                    <a
                        href="{{ route('jirau.show', $navBoard) }}"
                        data-admin-close
                        @class(['admin-nav__link', 'is-active' => $currentJirau && (int) $currentJirau->id === (int) $navBoard->id])
                    >
                        <span class="admin-nav__icon">
                            <x-ui.icon name="jirau" class="h-4 w-4" />
                        </span>
                        <span class="admin-nav__text">
                            {{ $navBoard->project?->name ?? $navBoard->name }}
                            @unless($navBoard->is_active)
                                <small>inativo</small>
                            @endunless
                        </span>
                    </a>
                @empty
                    <p class="admin-nav__label" style="font-weight:500;text-transform:none;letter-spacing:0">Nenhum Jirau ainda.</p>
                @endforelse
            </div>
        </nav>

        <div class="admin-user">
            @if($jirauUser->avatarUrl())
                <img src="{{ $jirauUser->avatarUrl() }}" alt="" class="admin-user__avatar">
            @else
                <span class="admin-user__avatar admin-user__avatar--fallback">{{ $jirauUser->initials() }}</span>
            @endif
            <span class="admin-user__meta">
                <span class="admin-user__name">{{ $jirauUser->name }}</span>
                <span class="admin-user__handle">{{ $jirauUser->jirauTeamLabel() }}</span>
            </span>
        </div>
    </aside>

    <div class="admin-stage">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button type="button" class="admin-icon-btn admin-mobile-only" data-admin-open aria-label="Abrir menu">
                    <span class="admin-menu-lines" aria-hidden="true"></span>
                </button>
                <p class="admin-topbar__title admin-mobile-only">Jirau</p>
            </div>
            <div class="admin-topbar__right">
                <span class="admin-user admin-user--chip">
                    @if($jirauUser->avatarUrl())
                        <img src="{{ $jirauUser->avatarUrl() }}" alt="" class="admin-user__avatar">
                    @else
                        <span class="admin-user__avatar admin-user__avatar--fallback">{{ $jirauUser->initials() }}</span>
                    @endif
                    <span class="admin-user__meta">
                        <span class="admin-user__name">{{ $jirauFirstName }}</span>
                        <span class="admin-user__handle">{{ $jirauUser->jirauTeamLabel() }}</span>
                    </span>
                </span>
            </div>
        </header>

        <div class="admin-content">
            <x-ui.flash />
            @yield('content')
        </div>
    </div>
</div>
@endsection
