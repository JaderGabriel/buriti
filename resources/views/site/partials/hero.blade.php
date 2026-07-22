<section class="relative min-h-[100svh] overflow-hidden pt-20 sm:pt-24">
    <div class="pointer-events-none absolute inset-0 grid-glow"></div>
    <div class="pointer-events-none absolute -left-24 top-28 h-72 w-72 rounded-full bg-brand/20 blur-3xl logo-pulse"></div>
    <div class="pointer-events-none absolute right-0 top-40 h-80 w-80 rounded-full bg-brand-bright/10 blur-3xl"></div>

    <div class="relative mx-auto grid min-h-[calc(100svh-5rem)] max-w-6xl items-center gap-10 px-4 pb-16 sm:px-5 lg:grid-cols-[1.05fr_0.95fr] lg:gap-12">
        <div class="reveal max-w-2xl">
            <p class="section-kicker">BURI-TI · buriti.dev.br</p>
            <h1 class="mt-4 font-display text-4xl font-extrabold tracking-[0.08em] text-snow sm:text-5xl md:text-6xl">
                BURI-TI
            </h1>
            <p class="font-script reveal-delay-1 mt-2 text-2xl text-brand md:text-3xl">Tecnologia para Pessoas</p>
            <p class="reveal-delay-2 mt-5 max-w-xl text-sm leading-relaxed text-mist sm:text-base md:text-lg">
                Consultoria, software, BI e integrações que transformam dados e processos em decisão — com parceria próxima e entrega que gera valor.
            </p>
            <div class="reveal-delay-3 mt-8 flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                <x-ui.button href="#contato">Solicitar proposta</x-ui.button>
                <x-ui.button href="#expertise" variant="secondary">Ver expertise</x-ui.button>
            </div>
        </div>

        <div class="reveal reveal-delay-2 relative mx-auto w-full max-w-md lg:max-w-none">
            <div class="absolute -inset-4 rounded-[2rem] bg-brand/10 blur-2xl"></div>
            <div class="relative overflow-hidden rounded-[1.75rem] border border-line bg-panel/80 p-8 text-center shadow-[0_30px_80px_rgba(5,7,11,0.25)]">
                <img
                    src="{{ asset('images/logo-buriti.png') }}"
                    alt="BURI-TI"
                    class="mx-auto h-28 w-28 object-contain drop-shadow-[0_0_40px_rgba(30,112,191,0.45)] md:h-36 md:w-36"
                >
                <p class="mt-6 text-xs font-semibold uppercase tracking-[0.2em] text-mist">Atuação</p>
                <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="font-display text-2xl font-bold text-brand-bright">BI</p>
                        <p class="mt-1 text-[11px] text-mist">Power BI & painéis</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl font-bold text-brand-bright">TI</p>
                        <p class="mt-1 text-[11px] text-mist">Laravel & dados</p>
                    </div>
                    <div>
                        <p class="font-display text-2xl font-bold text-brand-bright">GP</p>
                        <p class="mt-1 text-[11px] text-mist">Projetos ágeis</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
