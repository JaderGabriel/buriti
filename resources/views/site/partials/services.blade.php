<section id="servicos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">O que fazemos</p>
            <h2 class="section-title">Serviços com escopo claro</h2>
            <p class="section-lead">Consultoria, sistemas, Moodle, indicadores e suporte — o que sustenta o contrato no dia a dia.</p>
        </div>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($services as $service)
                <article class="site-card site-service">
                    <div class="site-service__icon">
                        <x-ui.icon :name="$service['icon'] ?? 'code'" class="h-5 w-5" />
                    </div>
                    <h3 class="font-display text-xl font-semibold text-snow">{{ $service['title'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-mist">{{ $service['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
