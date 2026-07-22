@extends('layouts.app')

@section('body')
    <header
        class="fixed inset-x-0 top-0 z-50 border-b border-line/80 bg-surface backdrop-blur-xl"
        x-data="{ open: false }"
        @keydown.escape.window="open = false"
    >
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-5 sm:py-3.5">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 sm:gap-3">
                <img src="{{ asset('images/logo-buriti.png') }}" alt="BURI-TI" class="h-9 w-9 object-contain sm:h-10 sm:w-10">
                <div class="min-w-0 leading-tight">
                    <span class="font-display text-xs font-bold tracking-[0.18em] text-snow sm:text-sm">BURI-TI</span>
                    <p class="font-script truncate text-sm leading-none text-brand sm:text-base">Tecnologia para Pessoas</p>
                </div>
            </a>

            <nav class="hidden items-center gap-5 text-sm text-mist xl:flex">
                <a href="#sobre" class="transition hover:text-snow">Sobre</a>
                <a href="#servicos" class="transition hover:text-snow">Serviços</a>
                <a href="#expertise" class="transition hover:text-snow">Expertise</a>
                <a href="#projetos" class="transition hover:text-snow">Portfólio</a>
                <a href="#contato" class="transition hover:text-snow">Contato</a>
            </nav>

            <div class="flex items-center gap-2">
                <div x-data="themeToggle" class="flex items-center">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-line text-snow transition hover:border-brand-bright/50"
                        @click="toggle()"
                        :aria-label="dark ? 'Ativar modo claro' : 'Ativar modo escuro'"
                    >
                        <svg x-show="dark" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.72 0-1.41 1.41M7.05 16.95l-1.41 1.41M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/></svg>
                        <svg x-show="!dark" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 14.3A8.1 8.1 0 1 1 9.7 3 6.5 6.5 0 0 0 21 14.3z"/></svg>
                    </button>
                </div>

                <x-ui.button href="#contato" class="hidden px-4 py-2 lg:inline-flex">Falar com a BURI-TI</x-ui.button>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-line text-snow xl:hidden"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-controls="mobile-nav"
                    aria-label="Abrir menu"
                >
                    <svg x-show="!open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 7h16M4 12h16M4 17h16"/></svg>
                    <svg x-show="open" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </div>

        <div
            id="mobile-nav"
            x-cloak
            x-show="open"
            x-transition.origin.top
            class="border-t border-line bg-panel/95 xl:hidden"
        >
            <nav class="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-4 text-sm">
                <a href="#sobre" class="rounded-xl px-3 py-3 text-mist hover:bg-white/5 hover:text-snow" @click="open = false">Sobre</a>
                <a href="#servicos" class="rounded-xl px-3 py-3 text-mist hover:bg-white/5 hover:text-snow" @click="open = false">Serviços</a>
                <a href="#expertise" class="rounded-xl px-3 py-3 text-mist hover:bg-white/5 hover:text-snow" @click="open = false">Expertise</a>
                <a href="#projetos" class="rounded-xl px-3 py-3 text-mist hover:bg-white/5 hover:text-snow" @click="open = false">Portfólio</a>
                <a href="#contato" class="rounded-xl px-3 py-3 text-mist hover:bg-white/5 hover:text-snow" @click="open = false">Contato</a>
                <x-ui.button href="#contato" class="mt-2 w-full" @click="open = false">Solicitar proposta</x-ui.button>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-line bg-panel/80">
        <div class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-10 sm:px-5 md:flex-row md:items-end md:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <img src="{{ asset('images/logo-buriti.png') }}" alt="" class="h-9 w-9 object-contain">
                    <span class="font-display text-lg font-bold tracking-wide">BURI-TI</span>
                </div>
                <p class="mt-2 max-w-md text-sm text-mist">Consultoria, software, BI e integrações educacionais — do diagnóstico à entrega, com foco em pessoas e resultados.</p>
            </div>
            <div class="flex flex-wrap gap-4 text-sm text-mist">
                @if($githubUrl ?? false)
                    <a href="{{ $githubUrl }}" target="_blank" rel="noopener" class="hover:text-snow">GitHub</a>
                @endif
                @if($linkedinUrl ?? false)
                    <a href="{{ $linkedinUrl }}" target="_blank" rel="noopener" class="hover:text-snow">LinkedIn</a>
                @endif
                @if($telegramUrl ?? false)
                    <a href="{{ $telegramUrl }}" target="_blank" rel="noopener" class="hover:text-snow">{{ $telegramHandle ?? 'Telegram' }}</a>
                @endif
                <a href="{{ route('login') }}" class="hover:text-snow">Admin</a>
            </div>
        </div>
        <div class="border-t border-line px-4 py-4 text-center text-xs text-mist/70 sm:px-5">
            © {{ date('Y') }} BURI-TI · buriti.dev.br
        </div>
    </footer>
@endsection
