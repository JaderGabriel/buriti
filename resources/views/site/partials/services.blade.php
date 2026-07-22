<section id="servicos" class="section-shell">
    <div class="mx-auto max-w-6xl px-4 sm:px-5">
        <div class="max-w-2xl">
            <p class="section-kicker">Serviços</p>
            <h2 class="section-title">O que a BURI-TI entrega</h2>
            <p class="mt-4 text-sm text-mist sm:text-base">Oferta clara para quem precisa de TI e dados confiáveis — do software ao BI, sem ruído.</p>
        </div>

        <div class="mt-10 grid gap-6 sm:mt-12 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($services as $service)
                <article class="group border-b border-line pb-6 transition hover:border-brand-bright/40">
                    <h3 class="font-display text-lg font-semibold text-snow group-hover:text-brand-bright sm:text-xl">{{ $service['title'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-mist">{{ $service['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
