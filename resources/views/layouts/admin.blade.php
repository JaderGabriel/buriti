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
                    'label' => 'Dashboard',
                    'pattern' => 'admin.dashboard',
                    'icon' => 'dashboard',
                    'tone' => 'text-brand-bright bg-brand/15',
                    'active' => 'bg-brand/25 text-brand-bright ring-1 ring-brand/40',
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
                    'tone' => 'text-amber-300 bg-amber-500/15',
                    'active' => 'bg-amber-500/20 text-amber-200 ring-1 ring-amber-400/35',
                ],
                [
                    'route' => 'admin.contacts.index',
                    'label' => 'Contatos',
                    'pattern' => 'admin.contacts.*',
                    'icon' => 'contact',
                    'tone' => 'text-sky-300 bg-sky-500/15',
                    'active' => 'bg-sky-500/20 text-sky-200 ring-1 ring-sky-400/35',
                ],
                [
                    'route' => 'admin.opportunities.index',
                    'label' => 'Oportunidades',
                    'pattern' => 'admin.opportunities.*',
                    'icon' => 'opportunity',
                    'tone' => 'text-emerald-300 bg-emerald-500/15',
                    'active' => 'bg-emerald-500/20 text-emerald-200 ring-1 ring-emerald-400/35',
                ],
            ],
        ],
        [
            'label' => 'Entrega',
            'items' => [
                [
                    'route' => 'admin.projects.index',
                    'label' => 'Projetos',
                    'pattern' => 'admin.projects.*',
                    'icon' => 'project',
                    'tone' => 'text-cyan-300 bg-cyan-500/15',
                    'active' => 'bg-cyan-500/20 text-cyan-200 ring-1 ring-cyan-400/35',
                ],
                [
                    'route' => 'admin.tasks.index',
                    'label' => 'Tarefas',
                    'pattern' => 'admin.tasks.*',
                    'icon' => 'task',
                    'tone' => 'text-orange-300 bg-orange-500/15',
                    'active' => 'bg-orange-500/20 text-orange-200 ring-1 ring-orange-400/35',
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
                        'tone' => 'text-rose-300 bg-rose-500/15',
                        'active' => 'bg-rose-500/20 text-rose-200 ring-1 ring-rose-400/35',
                    ]
                    : null,
                [
                    'route' => 'admin.integrations.edit',
                    'label' => 'Integrações',
                    'pattern' => 'admin.integrations.*',
                    'icon' => 'integrations',
                    'tone' => 'text-lime-300 bg-lime-500/15',
                    'active' => 'bg-lime-500/20 text-lime-200 ring-1 ring-lime-400/35',
                ],
                [
                    'route' => 'admin.settings.edit',
                    'label' => 'Configurações',
                    'pattern' => 'admin.settings.*',
                    'icon' => 'settings',
                    'tone' => 'text-mist bg-white/5',
                    'active' => 'bg-white/10 text-snow ring-1 ring-line',
                ],
            ])),
        ],
    ];
@endphp

<div class="min-h-screen lg:grid lg:grid-cols-[240px_1fr]" data-admin-shell>
    <div
        class="fixed inset-0 z-40 hidden bg-black/60 lg:hidden"
        data-admin-overlay
        hidden
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-[min(100%,18rem)] flex-col border-r border-line bg-panel transition-transform max-lg:-translate-x-full max-lg:data-[open=true]:translate-x-0 lg:static"
        data-admin-sidebar
        data-open="false"
    >
        <div class="flex items-center justify-between gap-3 px-5 py-4">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="h-9 w-9 object-contain">
                <div class="min-w-0">
                    <p class="font-display text-sm font-bold tracking-wide">BURI-TI</p>
                    <p class="text-xs text-mist">Painel admin</p>
                </div>
            </div>
            <button type="button" class="rounded-sm border border-line p-2 text-mist lg:hidden" data-admin-close aria-label="Fechar menu">✕</button>
        </div>

        <div class="mx-3 mb-3 grid grid-cols-3 gap-2">
            <a
                href="{{ route('home') }}"
                class="inline-flex h-10 items-center justify-center rounded-sm border border-line text-sky-300 transition hover:border-sky-400/40 hover:bg-sky-500/10 hover:text-sky-200"
                title="Ver site"
                aria-label="Ver site"
            >
                <x-ui.icon name="external" class="h-4 w-4" />
            </a>
            <button
                type="button"
                data-theme-toggle
                class="inline-flex h-10 items-center justify-center rounded-sm border border-line text-amber-300 transition hover:border-amber-400/40 hover:bg-amber-500/10 hover:text-amber-200"
                aria-label="Ativar modo escuro"
                aria-pressed="false"
                title="Alternar tema"
            >
                <x-ui.icon name="sun" class="hidden h-4 w-4 dark:block" />
                <x-ui.icon name="moon" class="block h-4 w-4 dark:hidden" />
            </button>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex h-10 w-full items-center justify-center rounded-sm border border-line text-rose-300 transition hover:border-rose-400/40 hover:bg-rose-500/10 hover:text-rose-200"
                    title="Sair"
                    aria-label="Sair"
                >
                    <x-ui.icon name="logout" class="h-4 w-4" />
                </button>
            </form>
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
            @foreach($navGroups as $group)
                <div class="flex flex-col gap-1">
                    @if($group['label'])
                        <p class="px-3 pb-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-mist/70">{{ $group['label'] }}</p>
                    @endif
                    @foreach($group['items'] as $item)
                        @php $active = request()->routeIs($item['pattern']); @endphp
                        <a href="{{ route($item['route']) }}"
                           data-admin-close
                           @class([
                               'group flex items-center gap-3 rounded-sm px-2.5 py-2 transition',
                               $item['active'] => $active,
                               'text-mist hover:bg-ink hover:text-snow' => ! $active,
                           ])>
                            <span @class([
                                'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-sm',
                                $item['tone'],
                            ])>
                                <x-ui.icon :name="$item['icon']" class="h-4 w-4" />
                            </span>
                            <span class="font-medium">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="min-w-0">
        <div class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-line bg-ink/90 px-4 py-3 backdrop-blur lg:hidden">
            <div class="flex items-center gap-3">
                <button type="button" class="rounded-sm border border-line px-3 py-2 text-sm" data-admin-open>Menu</button>
                <p class="font-display text-sm font-semibold">Admin BURI-TI</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('home') }}" class="inline-flex h-9 w-9 items-center justify-center rounded-sm border border-line text-sky-300" aria-label="Ver site" title="Ver site">
                    <x-ui.icon name="external" class="h-4 w-4" />
                </a>
                <button
                    type="button"
                    data-theme-toggle
                    class="inline-flex h-9 w-9 items-center justify-center rounded-sm border border-line text-amber-300"
                    aria-label="Ativar modo escuro"
                    aria-pressed="false"
                    title="Alternar tema"
                >
                    <x-ui.icon name="sun" class="hidden h-4 w-4 dark:block" />
                    <x-ui.icon name="moon" class="block h-4 w-4 dark:hidden" />
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
