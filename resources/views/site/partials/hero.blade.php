<section class="hero-band relative min-h-[88svh] overflow-hidden">
    <div class="pointer-events-none absolute inset-0 grid-glow opacity-40"></div>

    <div class="relative mx-auto flex min-h-[calc(88svh-1rem)] max-w-7xl flex-col justify-center px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal max-w-4xl">
            <p class="section-kicker">Consultoria · Cloud apps · BI · Software</p>
            <h1 class="mt-5 font-display text-4xl font-extrabold leading-[1.02] tracking-tight sm:text-5xl lg:text-6xl xl:text-[4.25rem]">
                Infraestrutura de tecnologia com
                <span class="text-brand-bright"> desempenho comercial</span>.
            </h1>
            <p class="mt-4 text-lg font-medium tracking-wide text-[#7eb6e8] sm:text-xl">BURI-TI — Tecnologia para Pessoas</p>
            <p class="reveal-delay-1 mt-6 max-w-2xl text-base leading-relaxed text-mist sm:text-lg">
                No espírito das grandes plataformas de TI: clareza de oferta, engenharia sólida e indicadores de negócio.
                Da proposta à operação — software, Power BI e gestão de projetos.
            </p>
            <div class="reveal-delay-2 mt-9 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <x-ui.button href="#contato" class="min-w-[11rem]">Pedir proposta</x-ui.button>
                <x-ui.button href="#metodo" variant="secondary" class="min-w-[11rem] border-white/30 text-[#eef3f8] hover:border-white hover:text-white">Ver método</x-ui.button>
            </div>
        </div>

        <div class="reveal-delay-3 mt-16 grid gap-6 border-t border-white/15 pt-8 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'diagnose', 'label' => 'Diagnóstico', 'text' => 'Escopo e prioridades'],
                ['icon' => 'code', 'label' => 'Engenharia', 'text' => 'Laravel, dados e integrações'],
                ['icon' => 'bi', 'label' => 'BI & gestão', 'text' => 'Indicadores para decidir'],
            ] as $item)
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 inline-flex h-10 w-10 items-center justify-center rounded-sm border border-white/20 text-[#7eb6e8]">
                        <x-ui.icon :name="$item['icon']" class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-display text-lg font-semibold text-[#eef3f8]">{{ $item['label'] }}</p>
                        <p class="mt-1 text-sm text-[#b8c7d8]">{{ $item['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
