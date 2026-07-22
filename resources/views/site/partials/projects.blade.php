<section id="projetos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="section-kicker">Portfólio</p>
                <h2 class="section-title">Projetos e entregas reais</h2>
                <p class="mt-4 text-sm text-mist sm:text-base">
                    Cases públicos com repositório aberto e entregas confidenciais (repositório privado):
                    a capacidade fica visível; o código sensível, não.
                </p>
            </div>
            @if($githubUrl ?? false)
                <a href="{{ $githubUrl }}" target="_blank" rel="noopener" class="text-sm text-mist hover:text-brand-bright">
                    Ver GitHub público →
                </a>
            @endif
        </div>

        @if($projects->isEmpty())
            <p class="mt-10 text-mist">Em breve, novos cases. Fale conosco para conhecer entregas sob NDA.</p>
        @else
            @if($openSourceProjects->isNotEmpty())
                <div class="mt-10">
                    <div class="mb-5 flex items-end justify-between gap-3">
                        <h3 class="font-display text-xl font-semibold text-snow">Repositórios públicos</h3>
                        <p class="text-xs text-mist">Com links quando disponíveis</p>
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
                            <h3 class="font-display text-xl font-semibold text-snow">Repositórios privados</h3>
                            <p class="mt-1 max-w-2xl text-sm text-mist">
                                Parte do portfólio comercial: stack e resultado descritos, sem exposição de código ou URLs internas.
                            </p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-brand">Sob NDA / contrato</span>
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
