@extends('layouts.app')

@section('body')
    <div class="oracle-topbar hidden border-b border-line bg-panel text-xs text-mist sm:block">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-2 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-4">
                <a href="{{ route('home') }}#servicos" class="hover:text-snow">Produtos e serviços</a>
                <a href="{{ route('home') }}#projetos" class="hover:text-snow">Portfólio</a>
                <a href="{{ route('home') }}#contato" class="hover:text-snow">Contato</a>
            </div>
            <x-site.admin-link class="inline-flex items-center gap-1.5 font-semibold text-brand-bright hover:text-snow">
                <x-ui.icon name="admin" class="h-3.5 w-3.5" />
                @auth Painel admin @else Área admin @endauth
            </x-site.admin-link>
        </div>
    </div>

    <header class="sticky top-0 z-50 border-b border-line bg-panel" data-site-header>
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="BURI-TI" class="h-9 w-9 object-contain sm:h-10 sm:w-10">
                <div class="min-w-0 leading-tight">
                    <span class="font-display text-sm font-bold tracking-[0.12em] text-snow sm:text-base">BURI-TI</span>
                    <p class="truncate text-[11px] uppercase tracking-[0.18em] text-mist">Tecnologia para Pessoas</p>
                </div>
            </a>

            <nav class="hidden items-center gap-6 text-sm font-medium text-mist xl:flex">
                <a href="{{ route('home') }}#metodo" class="transition hover:text-snow">Método</a>
                <a href="{{ route('home') }}#servicos" class="transition hover:text-snow">Serviços</a>
                <a href="{{ route('home') }}#expertise" class="transition hover:text-snow">Expertise</a>
                <a href="{{ route('home') }}#projetos" class="transition hover:text-snow">Portfólio</a>
                <a href="{{ route('home') }}#equipe" class="transition hover:text-snow">Quem é quem</a>
                <a href="{{ route('home') }}#contato" class="transition hover:text-snow">Contato</a>
            </nav>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    data-theme-toggle
                    class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line bg-panel text-snow transition hover:border-brand-bright/50 hover:text-brand-bright"
                    aria-label="Ativar modo escuro"
                    aria-pressed="false"
                    title="Modo escuro"
                >
                    <svg class="hidden h-4 w-4 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.41 1.41M7.05 16.95l-1.41 1.41M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/></svg>
                    <svg class="block h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 14.3A8.1 8.1 0 1 1 9.7 3 6.5 6.5 0 0 0 21 14.3z"/></svg>
                </button>

                <x-ui.button href="{{ route('home') }}#contato" class="hidden px-4 py-2 xl:inline-flex">Pedir proposta</x-ui.button>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line text-snow xl:hidden"
                    data-nav-toggle
                    aria-expanded="false"
                    aria-controls="mobile-nav"
                    aria-label="Abrir menu"
                >
                    <svg data-nav-icon="open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg data-nav-icon="close" class="hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </div>

        <div id="mobile-nav" class="hidden border-t border-line bg-panel xl:hidden" data-nav-panel hidden>
            <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 text-sm sm:px-6">
                <a href="{{ route('home') }}#metodo" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Método</a>
                <a href="{{ route('home') }}#servicos" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Serviços</a>
                <a href="{{ route('home') }}#expertise" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Expertise</a>
                <a href="{{ route('home') }}#projetos" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Portfólio</a>
                <a href="{{ route('home') }}#equipe" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Quem é quem</a>
                <a href="{{ route('home') }}#contato" class="rounded-sm px-3 py-3 text-mist hover:bg-ink hover:text-snow" data-nav-close>Contato</a>
                <x-site.admin-link class="rounded-sm px-3 py-3 font-semibold text-brand-bright" data-nav-close>
                    @auth Painel admin @else Área admin @endauth
                </x-site.admin-link>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <a
        href="{{ route('home') }}#contato"
        class="mobile-proposal-fab xl:hidden"
        aria-label="Pedir proposta"
    >
        <x-ui.icon name="message" class="h-5 w-5" />
        <span>Pedir proposta</span>
    </a>

    <footer class="border-t border-line bg-panel">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-8 px-4 py-12 sm:gap-10 sm:px-6 md:grid-cols-3 lg:px-8">
            <div class="col-span-2 md:col-span-1">
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="h-9 w-9 object-contain">
                    <span class="font-display text-lg font-bold tracking-wide">BURI-TI</span>
                </div>
                <p class="mt-3 max-w-sm text-sm text-mist">Infraestrutura de software, dados e BI para operações que precisam de resultado — no estilo das grandes plataformas de tecnologia.</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-mist">Navegação</p>
                <div class="mt-3 flex flex-col gap-2 text-sm text-mist">
                    <a href="{{ route('home') }}#servicos" class="hover:text-snow">Serviços</a>
                    <a href="{{ route('home') }}#projetos" class="hover:text-snow">Portfólio</a>
                    <a href="{{ route('home') }}#contato" class="hover:text-snow">Contato</a>
                    <a href="{{ route('privacy') }}" class="hover:text-snow">Privacidade</a>
                    <a href="{{ route('cookies') }}" class="hover:text-snow">Cookies</a>
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-mist">Conta e redes</p>
                <div class="mt-3 space-y-3">
                    <x-site.admin-link class="inline-flex text-sm font-semibold text-brand-bright hover:text-snow">
                        @auth Abrir painel admin @else Área admin @endauth
                    </x-site.admin-link>
                    <x-site.contact-icons
                        :linkedin="$linkedinUrl ?? null"
                        :github="$githubUrl ?? null"
                        :telegram="$telegramUrl ?? null"
                        :telegram-handle="$telegramHandle ?? null"
                    />
                </div>
            </div>
        </div>
        <div class="border-t border-line px-4 py-5 text-center text-xs text-mist/80 sm:px-6">
            <p>© {{ date('Y') }} BURI-TI — Tecnologia para Pessoas. Todos os direitos reservados.</p>
            <p class="mt-1.5">
                Desenvolvido e mantido por <span class="text-snow">BURI-TI</span>
                ·
                <a href="https://buriti.dev.br" class="hover:text-snow">buriti.dev.br</a>
            </p>
            <p class="mt-2 flex flex-wrap items-center justify-center gap-x-3 gap-y-1">
                <a href="{{ route('privacy') }}" class="hover:text-snow">Política de Privacidade</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('cookies') }}" class="hover:text-snow">Política de Cookies</a>
                <span aria-hidden="true">·</span>
                <span>LGPD</span>
            </p>
        </div>
    </footer>

    <x-site.cookie-banner />
@endsection
