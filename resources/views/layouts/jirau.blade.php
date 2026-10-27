@extends('layouts.app')

@section('title', ($jirau?->name ?? 'Jirau').' — '.$product['name'])

@section('body')
@php
    $jirauUser = auth()->user();
    $jirauFirstName = explode(' ', trim($jirauUser->name))[0] ?? 'olá';
    $runtimeVersions = $runtimeVersions ?? \App\Support\RuntimeVersions::labels();
    $navJiraus = ($jirauUser->is_admin || $jirauUser->jirau_role)
        ? \App\Models\Jirau::query()->with('project')->orderBy('sort_order')->orderBy('name')->get()
        : $jirauUser->jiraus()->with('project')->orderBy('sort_order')->orderBy('name')->get();
    $currentJirau = $jirau ?? null;
    $modules = \App\Support\AdminNavigation::modules($jirauUser, (int) ($adminUnreadMessages ?? 0));
    $currentModule = \App\Support\AdminNavigation::current($modules);
    $moduleLabel = $currentModule['label'] ?? 'Jirau';
    $moduleItems = $jirauUser->is_admin
        ? ($currentModule['items'] ?? [])
        : $navJiraus->map(fn ($navBoard) => [
            'url' => route('jirau.show', $navBoard),
            'label' => $navBoard->name,
            'active' => $currentJirau && (int) $currentJirau->id === (int) $navBoard->id,
            'icon' => 'jirau',
        ])->all();

    if (! $jirauUser->is_admin) {
        $modules = array_map(function (array $module) use ($moduleItems): array {
            if (($module['id'] ?? '') === 'jirau') {
                $module['items'] = $moduleItems;
            }

            return $module;
        }, $modules);
    }
@endphp

<div class="admin-shell" data-admin-shell data-jirau-shell>
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

    <aside class="admin-sidebar" data-admin-sidebar data-open="false">
        <div class="admin-brand">
            <a href="{{ route('jirau.home') }}" class="admin-brand__link" data-admin-close title="Jirau">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="admin-brand__logo">
                <span class="admin-brand__text">
                    <span class="admin-brand__name">Jirau</span>
                    <span class="admin-brand__tag">Quadro ágil · <x-product.release as="short" /></span>
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

        @include('admin.partials.module-nav', ['modules' => $modules, 'runtimeVersions' => $jirauUser->is_admin ? $runtimeVersions : null])

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
                <button type="button" class="admin-icon-btn" data-admin-nav-toggle aria-label="Minimizar menu" title="Minimizar menu">
                    <span class="admin-menu-lines" aria-hidden="true"></span>
                </button>
                <p class="admin-topbar__title admin-mobile-only">Jirau</p>
                @include('admin.partials.modulebar', ['moduleItems' => $moduleItems, 'moduleLabel' => $moduleLabel])
            </div>
            <div class="admin-topbar__right">
                @if($jirauUser->is_admin)
                    <x-jirau.action tone="board" icon="plus" data-jirau-toggle="new-board">Novo quadro</x-jirau.action>
                @endif
                <div class="admin-topbar__account">
                    @include('admin.partials.notifications')
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
            </div>
        </header>

        <div class="admin-content">
            <x-ui.flash />
            @yield('content')
        </div>
    </div>

    @if($jirauUser->is_admin)
        @include('jirau.partials.create-board-dialog')
    @endif
</div>
@endsection
