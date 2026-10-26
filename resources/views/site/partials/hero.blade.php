<section class="hero-band relative overflow-hidden">
    <div class="pointer-events-none absolute inset-0 grid-glow opacity-30"></div>

    <div class="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-20 lg:px-8">
        <div class="hero-band__grid">
            <div class="reveal max-w-3xl lg:max-w-none">
                <p class="section-kicker">Consultoria · Sistemas · Moodle · Indicadores</p>
                <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl xl:text-[4.1rem]">
                    Tecnologia que o gestor entende
                    <span class="text-brand-bright"> e a operação usa</span>.
                </h1>
                <p class="mt-4 text-lg font-medium text-blue sm:text-xl">BURI-TI — Tecnologia para Pessoas</p>
                <p class="reveal-delay-1 mt-6 max-w-2xl text-base leading-relaxed text-mist sm:text-lg">
                    Consultoria, sistemas, Moodle e painéis de indicadores. Da proposta à operação, com escopo claro e acompanhamento próximo.
                </p>
                <div class="reveal-delay-2 mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                    <x-ui.button href="#contato" class="min-w-[11rem] rounded-full">Pedir proposta</x-ui.button>
                    <x-ui.button href="#metodo" variant="secondary" class="min-w-[11rem] rounded-full">Como trabalhamos</x-ui.button>
                </div>
            </div>

            <div class="hero-highlights reveal-delay-3">
                @foreach ([
                    ['icon' => 'diagnose', 'label' => 'Diagnóstico', 'text' => 'Escopo e prioridades'],
                    ['icon' => 'code', 'label' => 'Sistemas', 'text' => 'Laravel, Moodle e integrações'],
                    ['icon' => 'bi', 'label' => 'Indicadores', 'text' => 'Números para decidir'],
                ] as $item)
                    <div class="hero-highlight">
                        <span class="hero-highlight__icon" aria-hidden="true">
                            <x-ui.icon :name="$item['icon']" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="font-display text-lg font-semibold text-snow">{{ $item['label'] }}</p>
                            <p class="mt-1 text-sm text-mist">{{ $item['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
