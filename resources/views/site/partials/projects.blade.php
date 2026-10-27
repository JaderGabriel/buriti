<section id="projetos" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="max-w-3xl">
                <p class="section-kicker">Portfólio</p>
                <h2 class="section-title">Projetos e entregas reais</h2>
                <p class="section-lead">
                    Cada tipo vira como um caderno — passe as páginas para ver os itens.
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
            <div class="project-library mt-12">
                @include('site.partials.project-book', [
                    'bookId' => 'destaques',
                    'variant' => 'featured',
                    'kicker' => 'Destaques',
                    'title' => 'Projetos em destaque',
                    'lead' => 'Entregas públicas que representam o trabalho atual.',
                    'badge' => '★',
                    'items' => $featuredProjects ?? collect(),
                    'featuredCards' => true,
                    'perPage' => 2,
                ])

                @include('site.partials.project-book', [
                    'bookId' => 'codigo-aberto',
                    'variant' => 'open',
                    'kicker' => 'Público',
                    'title' => 'Código aberto',
                    'lead' => 'Repositórios com código visível.',
                    'items' => $openSourceProjects,
                    'perPage' => 3,
                ])

                @include('site.partials.project-book', [
                    'bookId' => 'confidenciais',
                    'variant' => 'private',
                    'kicker' => 'Contrato / NDA',
                    'title' => 'Projetos restritos',
                    'lead' => 'Tecnologias e resultado descritos no site, com responsabilidade da propriedade contratual.',
                    'badge' => 'NDA',
                    'items' => $privateRepoProjects,
                    'cardClass' => 'border-brand/20 bg-[linear-gradient(160deg,rgba(30,112,191,0.08),transparent_50%)]',
                    'perPage' => 3,
                ])
            </div>
        @endif
    </div>
</section>
