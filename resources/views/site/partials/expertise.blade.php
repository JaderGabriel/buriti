<section id="expertise" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Experiência</p>
            <h2 class="section-title">Gestão e tecnologia na prática</h2>
            <p class="mt-4 text-sm leading-relaxed text-mist sm:text-base">{{ $expertise['intro'] }}</p>
        </div>

        @if (! empty($expertise['consolidated']))
            @php($consolidated = $expertise['consolidated'])
            <div class="mt-12 overflow-hidden rounded-[1.5rem] border border-brand/35 bg-panel/80">
                <div class="border-b border-line bg-[radial-gradient(ellipse_at_top_left,rgba(30,112,191,0.18),transparent_55%)] px-6 py-7 sm:px-8 sm:py-8">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $consolidated['eyebrow'] }}</p>
                    <h3 class="mt-2 font-display text-2xl font-semibold text-snow sm:text-3xl">{{ $consolidated['title'] }}</h3>
                    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-mist sm:text-base">{{ $consolidated['intro'] }}</p>
                    @if (! empty($linkedinUrl))
                        <p class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-mist">
                            <span>{{ $consolidated['note'] ?? '' }}</span>
                            <a
                                href="{{ $linkedinUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="font-semibold text-brand-bright underline-offset-2 hover:underline"
                            >{{ $consolidated['linkedin_label'] ?? 'LinkedIn' }}</a>
                        </p>
                    @endif
                </div>
                <div class="grid gap-0 sm:grid-cols-3">
                    @foreach ($consolidated['items'] as $item)
                        <article @class([
                            'border-t border-line p-6 sm:border-t-0 sm:p-7',
                            'sm:border-l sm:border-line' => ! $loop->first,
                        ])>
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line text-brand-bright">
                                <x-ui.icon :name="$item['icon'] ?? 'train'" class="h-5 w-5" />
                            </span>
                            <h4 class="mt-4 font-display text-lg font-semibold text-snow">{{ $item['title'] }}</h4>
                            <p class="mt-2 text-sm leading-relaxed text-mist">{{ $item['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-12 grid gap-8 lg:grid-cols-2">
            <div class="rounded-[1.5rem] border border-line bg-panel/70 p-6 sm:p-8">
                <h3 class="font-display text-xl font-semibold">Visão gerencial</h3>
                <p class="mt-2 text-sm text-mist">Como conduzimos projetos, processos e capacitação com foco em resultado.</p>
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
                <p class="mt-2 text-sm text-mist">Linguagens, plataformas e ferramentas usadas na entrega do dia a dia.</p>
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
            <h3 class="font-display text-xl font-semibold">Indicadores e dados</h3>
            <p class="mt-2 max-w-2xl text-sm text-mist">Do dado operacional ao número que a gestão usa — Power BI, importações e plataformas no contexto educacional.</p>
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
