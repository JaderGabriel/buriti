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
        class="fixed inset-y-0 left-0 z-50 flex w-[min(100%,18rem)] -translate-x-full flex-col border-r border-line bg-panel transition-transform lg:static lg:translate-x-0"
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
            <button type="button" class="rounded-sm border border-line p-2 text-mist lg:hidden" @click="sidebarOpen = false" aria-label="Fechar menu">✕</button>
        </div>

        <div class="mx-3 mb-4 flex items-center gap-3 rounded-sm border border-line bg-ink/40 px-3 py-3">
            @if(auth()->user()->avatarUrl())
                <img src="{{ auth()->user()->avatarUrl() }}" alt="" class="h-10 w-10 rounded-sm object-cover">
            @else
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line text-xs font-semibold text-brand-bright">{{ auth()->user()->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-sm font-medium text-snow">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-mist">{{ '@'.auth()->user()->username }}</p>
            </div>
        </div>

        <nav class="flex min-h-0 flex-1 flex-col gap-5 overflow-y-auto px-3 pb-6 text-sm" aria-label="Menu do painel">
            @php
                /**
                 * Fluxo: visão → comercial (entrada) → entrega → sistema.
                 * Mensagem do site → Contato CRM → Oportunidade → Projeto → Tarefa.
                 */
                $navGroups = [
                    [
                        'label' => null,
                        'items' => [
                            ['route' => 'admin.dashboard', 'label' => 'Dashboard', 'pattern' => 'admin.dashboard'],
                        ],
                    ],
                    [
                        'label' => 'Comercial',
                        'items' => [
                            ['route' => 'admin.messages.index', 'label' => 'Mensagens', 'pattern' => 'admin.messages.*'],
                            ['route' => 'admin.contacts.index', 'label' => 'Contatos', 'pattern' => 'admin.contacts.*'],
                            ['route' => 'admin.opportunities.index', 'label' => 'Oportunidades', 'pattern' => 'admin.opportunities.*'],
                        ],
                    ],
                    [
                        'label' => 'Entrega',
                        'items' => [
                            ['route' => 'admin.projects.index', 'label' => 'Projetos', 'pattern' => 'admin.projects.*'],
                            ['route' => 'admin.tasks.index', 'label' => 'Tarefas', 'pattern' => 'admin.tasks.*'],
                        ],
                    ],
                    [
                        'label' => 'Sistema',
                        'items' => array_values(array_filter([
                            auth()->user()->is_admin
                                ? ['route' => 'admin.users.index', 'label' => 'Usuários', 'pattern' => 'admin.users.*']
                                : null,
                            ['route' => 'admin.integrations.edit', 'label' => 'Integrações', 'pattern' => 'admin.integrations.*'],
                            ['route' => 'admin.settings.edit', 'label' => 'Configurações', 'pattern' => 'admin.settings.*'],
                        ])),
                    ],
                ];
            @endphp

            @foreach($navGroups as $group)
                <div class="flex flex-col gap-1">
                    @if($group['label'])
                        <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-mist/70">{{ $group['label'] }}</p>
                    @endif
                    @foreach($group['items'] as $item)
                        <a href="{{ route($item['route']) }}"
                           @click="sidebarOpen = false"
                           @class([
                               'rounded-sm px-3 py-2.5 transition',
                               'bg-brand/20 text-brand-bright' => request()->routeIs($item['pattern']),
                               'text-mist hover:bg-ink hover:text-snow' => ! request()->routeIs($item['pattern']),
                           ])>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            @endforeach

            <div class="mt-auto border-t border-line pt-3">
                <a href="{{ route('home') }}"
                   @click="sidebarOpen = false"
                   class="block rounded-sm px-3 py-2.5 text-mist transition hover:bg-ink hover:text-snow">
                    Ver site
                </a>

                <div class="mt-1" x-data="themeToggle">
                    <button
                        type="button"
                        data-theme-toggle
                        class="flex w-full items-center justify-between gap-3 rounded-sm px-3 py-2.5 text-left text-mist transition hover:bg-ink hover:text-snow"
                        aria-label="Ativar modo escuro"
                        aria-pressed="false"
                        title="Modo escuro"
                    >
                        <span class="inline-flex items-center gap-2">
                            <svg class="hidden h-4 w-4 shrink-0 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.41 1.41M7.05 16.95l-1.41 1.41M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
                            </svg>
                            <svg class="block h-4 w-4 shrink-0 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 14.3A8.1 8.1 0 1 1 9.7 3 6.5 6.5 0 0 0 21 14.3z"/>
                            </svg>
                            <span x-text="dark ? 'Modo claro' : 'Modo escuro'"></span>
                        </span>
                        <span class="text-[10px] uppercase tracking-wide text-mist/80" x-text="dark ? 'Escuro' : 'Claro'"></span>
                    </button>
                </div>

                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="w-full rounded-sm px-3 py-2.5 text-left text-mist transition hover:bg-ink hover:text-snow">Sair</button>
                </form>
            </div>
        </nav>
    </aside>

    <div class="min-w-0">
        <div class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-line bg-ink/90 px-4 py-3 backdrop-blur lg:hidden">
            <div class="flex items-center gap-3">
                <button type="button" class="rounded-sm border border-line px-3 py-2 text-sm" @click="sidebarOpen = true">Menu</button>
                <p class="font-display text-sm font-semibold">Admin BURI-TI</p>
            </div>
            <div>
                <button
                    type="button"
                    data-theme-toggle
                    class="inline-flex h-9 w-9 items-center justify-center rounded-sm border border-line text-snow"
                    aria-label="Ativar modo escuro"
                    aria-pressed="false"
                >
                    <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.41 1.41M7.05 16.95l-1.41 1.41M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/></svg>
                    <svg class="block h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 14.3A8.1 8.1 0 1 1 9.7 3 6.5 6.5 0 0 0 21 14.3z"/></svg>
                </button>
            </div>
        </div>

        <div class="px-4 py-6 sm:px-6 md:px-8">
            <x-ui.flash />
            @yield('content')
        </div>
    </div>
</div>
@endsection
