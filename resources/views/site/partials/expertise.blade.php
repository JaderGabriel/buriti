<section id="expertise" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Expertise</p>
            <h2 class="section-title">Modelagem gerencial e técnica</h2>
            <p class="mt-4 text-sm leading-relaxed text-mist sm:text-base">{{ $expertise['intro'] }}</p>
        </div>

        <div class="mt-12 grid gap-8 lg:grid-cols-2">
            <div class="rounded-[1.5rem] border border-line bg-panel/70 p-6 sm:p-8">
                <h3 class="font-display text-xl font-semibold">Visão gerencial</h3>
                <p class="mt-2 text-sm text-mist">Capacidade de conduzir projetos, processos e capacitação com foco em resultado de negócio.</p>
                <ul class="mt-8 space-y-6">
                    @foreach($expertise['managerial'] as $item)
                        <li>
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p class="font-medium text-snow">{{ $item['title'] }}</p>
                                    <p class="mt-1 text-xs text-mist sm:text-sm">{{ $item['description'] }}</p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-brand-bright">{{ $item['level'] }}%</span>
                            </div>
                            <div class="skill-bar mt-3" aria-hidden="true">
                                <span style="width: {{ $item['level'] }}%"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-[1.5rem] border border-line bg-panel/70 p-6 sm:p-8">
                <h3 class="font-display text-xl font-semibold">Stack e ferramentas</h3>
                <p class="mt-2 text-sm text-mist">Linguagens, plataformas e ecossistemas usados na entrega técnica do dia a dia.</p>
                <div class="mt-8 space-y-6">
                    @foreach($expertise['technical'] as $group)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $group['group'] }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($group['items'] as $tool)
                                    <span class="rounded-full border border-line px-3 py-1 text-xs text-snow sm:text-sm">{{ $tool }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-8">
            <h3 class="font-display text-xl font-semibold">Experiência em BI e dados</h3>
            <p class="mt-2 max-w-2xl text-sm text-mist">Do dado operacional ao indicador gerencial — Power BI, ETL e plataformas analíticas no ecossistema educacional.</p>
            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                @foreach($expertise['bi'] as $item)
                    <article class="border-b border-line pb-5">
                        <h4 class="font-display text-lg font-semibold text-snow">{{ $item['title'] }}</h4>
                        <p class="mt-2 text-sm leading-relaxed text-mist">{{ $item['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
