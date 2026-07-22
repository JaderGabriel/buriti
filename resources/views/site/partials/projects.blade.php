<section id="projetos" class="section-shell">
    <div class="mx-auto max-w-6xl px-4 sm:px-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-2xl">
                <p class="section-kicker">Portfólio</p>
                <h2 class="section-title">Projetos e entregas reais</h2>
                <p class="mt-4 text-sm text-mist sm:text-base">Seleção baseada no GitHub e na atuação com i-Educar, BI, integrações e plataformas educacionais.</p>
            </div>
            @if($githubUrl ?? false)
                <a href="{{ $githubUrl }}" target="_blank" rel="noopener" class="text-sm text-mist hover:text-brand-bright">
                    Ver GitHub →
                </a>
            @endif
        </div>

        @if($projects->isEmpty())
            <p class="mt-10 text-mist">Em breve, novos cases públicos. Fale conosco para conhecer entregas sob NDA.</p>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                @foreach($projects as $project)
                    <x-site.project-card :project="$project" />
                @endforeach
            </div>
        @endif
    </div>
</section>
