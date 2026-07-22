@extends('layouts.app')

@section('title', 'Admin — BURI-TI')

@section('body')
<div class="min-h-screen lg:grid lg:grid-cols-[240px_1fr]" x-data="{ sidebarOpen: false }">
    <div
        x-cloak
        x-show="sidebarOpen"
        class="fixed inset-0 z-40 bg-black/60 lg:hidden"
        @click="sidebarOpen = false"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 w-[min(100%,18rem)] -translate-x-full border-r border-line bg-panel transition-transform lg:static lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    >
        <div class="flex items-center justify-between gap-3 px-5 py-5">
            <div class="flex items-center gap-3">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="h-9 w-9 object-contain">
                <div>
                    <p class="font-display text-sm font-bold tracking-wide">BURI-TI</p>
                    <p class="text-xs text-mist">Painel admin</p>
                </div>
            </div>
            <button type="button" class="rounded-lg border border-line p-2 text-mist lg:hidden" @click="sidebarOpen = false" aria-label="Fechar menu">✕</button>
        </div>

        <nav class="flex flex-col gap-1 px-3 pb-6 text-sm">
            @php
                $links = [
                    ['admin.dashboard', 'Dashboard', 'admin.dashboard'],
                    ['admin.messages.index', 'Mensagens', 'admin.messages.*'],
                    ['admin.projects.index', 'Projetos', 'admin.projects.*'],
                    ['admin.tasks.index', 'Tarefas', 'admin.tasks.*'],
                    ['admin.settings.edit', 'Configurações', 'admin.settings.*'],
                ];
            @endphp
            @foreach($links as [$route, $label, $pattern])
                <a href="{{ route($route) }}"
                   @click="sidebarOpen = false"
                   class="rounded-lg px-3 py-2.5 {{ request()->routeIs($pattern) ? 'bg-brand/20 text-brand-bright' : 'text-mist hover:bg-white/5 hover:text-snow' }}">
                    {{ $label }}
                </a>
            @endforeach
            <a href="{{ route('home') }}" class="rounded-lg px-3 py-2.5 text-mist hover:bg-white/5 hover:text-snow">Ver site</a>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button class="w-full rounded-lg px-3 py-2.5 text-left text-mist hover:bg-white/5 hover:text-snow">Sair</button>
            </form>
        </nav>
    </aside>

    <div class="min-w-0">
        <div class="sticky top-0 z-30 flex items-center gap-3 border-b border-line bg-ink/90 px-4 py-3 backdrop-blur lg:hidden">
            <button type="button" class="rounded-lg border border-line px-3 py-2 text-sm" @click="sidebarOpen = true">Menu</button>
            <p class="font-display text-sm font-semibold">Admin BURI-TI</p>
        </div>

        <div class="px-4 py-6 sm:px-6 md:px-8">
            <x-ui.flash />
            @yield('content')
        </div>
    </div>
</div>
@endsection
