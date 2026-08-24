<section id="projetos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="section-kicker">Portfólio</p>
                <h2 class="section-title">Projetos e entregas reais</h2>
                <p class="section-lead">
                    Projetos com código aberto e entregas sob contrato. O que é público aparece aqui; o que é confidencial permanece protegido.
                </p>
            </div>
            @if($githubUrl ?? false)
                <a href="{{ $githubUrl }}" target="_blank" rel="noopener" class="text-sm text-mist hover:text-brand-bright">
                    Ver GitHub →
                </a>
            @endif
        </div>

        @if($projects->isEmpty())
            <p class="mt-10 text-mist">Em breve, novos projetos. Fale conosco para conhecer entregas sob contrato.</p>
        @else
            @if(($featuredProjects ?? collect())->isNotEmpty())
                <div class="home-featured mt-12">
                    <div class="home-featured__head">
                        <div>
                            <p class="section-kicker">Destaques</p>
                            <h3 class="font-display text-2xl font-semibold text-snow sm:text-3xl">Projetos em destaque</h3>
                            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-mist">Entregas públicas que representam o trabalho atual.</p>
                        </div>
                        <span class="home-featured__badge" aria-hidden="true">★</span>
                    </div>
                    <div class="home-featured__grid">
                        @foreach($featuredProjects as $index => $project)
                            <x-site.project-card
                                :project="$project"
                                :featured="true"
                                class="{{ $index === 0 && $featuredProjects->count() > 1 ? 'home-featured__lead' : '' }}"
                            />
                        @endforeach
                    </div>
                </div>
            @endif

            @if($openSourceProjects->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-5 flex items-end justify-between gap-3">
                        <h3 class="font-display text-xl font-semibold text-snow">Código aberto</h3>
                        <p class="text-xs text-mist">Repositórios com código visível</p>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($openSourceProjects as $project)
                            <x-site.project-card :project="$project" />
                        @endforeach
                    </div>
                </div>
            @endif

            @if($privateRepoProjects->isNotEmpty())
                <div class="mt-14">
                    <div class="mb-5 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="font-display text-xl font-semibold text-snow">Projetos confidenciais</h3>
                            <p class="mt-1 max-w-2xl text-sm text-mist">
                                Tecnologias e resultado descritos no site, sem expor código nem endereços internos.
                            </p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-brand">Contrato / NDA</span>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach($privateRepoProjects as $project)
                            <x-site.project-card :project="$project" class="border-brand/20 bg-[linear-gradient(160deg,rgba(30,112,191,0.08),transparent_50%)]" />
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>
</section>
