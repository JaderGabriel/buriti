@extends('layouts.app')

@section('title', 'Admin — BURI-TI')

@section('body')
@php
    $adminUser = auth()->user();
    $adminFirstName = explode(' ', trim($adminUser->name))[0] ?? 'olá';
    $runtimeVersions = $runtimeVersions ?? \App\Support\RuntimeVersions::labels();
    $modules = \App\Support\AdminNavigation::modules($adminUser, (int) ($adminUnreadMessages ?? 0));
    $currentModule = \App\Support\AdminNavigation::current($modules);
    $moduleItems = $currentModule['items'] ?? [];
    $moduleLabel = $currentModule['label'] ?? 'Painel';
@endphp

<div class="admin-shell" data-admin-shell>
    <script>
        (function () {
            try {
                var shell = document.currentScript.parentElement;
                if (localStorage.getItem('buriti-admin-nav') === 'collapsed' && window.matchMedia('(min-width: 1024px)').matches) {
                    shell.setAttribute('data-admin-collapsed', 'true');
                }
            } catch (e) {}
        })();
    </script>
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
            <a href="{{ route('admin.dashboard') }}" class="admin-brand__link" data-admin-close title="BURI-TI">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="admin-brand__logo">
                <span class="admin-brand__text">
                    <span class="admin-brand__name">BURI-TI</span>
                    <span class="admin-brand__tag">Painel admin</span>
                </span>
            </a>
            <button type="button" class="admin-icon-btn admin-icon-btn--ghost" data-admin-nav-toggle aria-label="Minimizar menu" title="Minimizar menu">
                <span class="admin-menu-lines" aria-hidden="true"></span>
            </button>
        </div>

        <div class="admin-rail-tools">
            @include('admin.partials.shortcuts')
            @include('admin.partials.module-pin', ['currentModule' => $currentModule])
        </div>

        @include('admin.partials.search', ['id' => 'admin-search', 'class' => 'admin-search--sidebar'])

        @include('admin.partials.module-nav', ['modules' => $modules, 'runtimeVersions' => $runtimeVersions])

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
                <button type="button" class="admin-icon-btn" data-admin-nav-toggle aria-label="Minimizar menu" title="Minimizar menu">
                    <span class="admin-menu-lines" aria-hidden="true"></span>
                </button>
                <p class="admin-topbar__title admin-mobile-only">Admin BURI-TI</p>
                @include('admin.partials.modulebar', ['moduleItems' => $moduleItems, 'moduleLabel' => $moduleLabel])
                @include('admin.partials.search', ['id' => 'admin-search-top', 'class' => 'admin-search--top'])
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

    @include('admin.tasks.partials.edit-dialog')

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
