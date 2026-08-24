<section id="servicos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">O que fazemos</p>
            <h2 class="section-title">Serviços com escopo claro</h2>
            <p class="section-lead">Consultoria, sistemas, Moodle, indicadores e suporte — o que sustenta o contrato no dia a dia.</p>
        </div>

        <div class="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($services as $service)
                <article class="group border-t border-line pt-6">
                    <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-sm border border-line text-brand-bright transition group-hover:border-brand-bright/50 group-hover:bg-brand/10">
                        <x-ui.icon :name="$service['icon'] ?? 'code'" class="h-5 w-5" />
                    </div>
                    <h3 class="font-display text-xl font-semibold text-snow group-hover:text-brand-bright">{{ $service['title'] }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-mist">{{ $service['description'] }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>
