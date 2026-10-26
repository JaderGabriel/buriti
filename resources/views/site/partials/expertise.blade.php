<section id="expertise" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Experiência</p>
            <h2 class="section-title">Gestão e tecnologia na prática</h2>
            <p class="section-lead">{{ $expertise['intro'] }}</p>
        </div>

        @if (! empty($expertise['consolidated']))
            @php($consolidated = $expertise['consolidated'])
            <div class="site-card mt-10 overflow-hidden">
                <div class="relative border-b border-line px-6 py-7 sm:px-8 sm:py-8">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(30,112,191,0.18),transparent_55%)]"></div>
                    <div class="relative">
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $consolidated['eyebrow'] }}</p>
                        <h3 class="mt-2 font-display text-2xl font-semibold text-snow sm:text-3xl">{{ $consolidated['title'] }}</h3>
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-mist sm:text-base">{{ $consolidated['intro'] }}</p>
                        @if (! empty($linkedinUrl))
                            <p class="mt-4 flex flex-wrap items-baseline gap-x-3 gap-y-1 text-sm text-mist">
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
                </div>
                <div class="grid sm:grid-cols-3 sm:items-stretch">
                    @foreach ($consolidated['items'] as $item)
                        <article @class([
                            'flex h-full flex-col px-6 py-7 sm:px-8 sm:py-8',
                            'border-t border-line sm:border-t-0',
                            'sm:border-l sm:border-line' => ! $loop->first,
                        ])>
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border border-line text-brand-bright">
                                <x-ui.icon :name="$item['icon'] ?? 'train'" class="h-5 w-5" />
                            </span>
                            <h4 class="mt-4 min-h-14 font-display text-lg font-semibold leading-snug text-snow">{{ $item['title'] }}</h4>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-mist">{{ $item['description'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-10 grid items-stretch gap-5 lg:grid-cols-2">
            <div class="site-card flex h-full flex-col p-6 sm:p-8">
                <h3 class="font-display text-xl font-semibold">Visão gerencial</h3>
                <p class="mt-2 text-sm leading-relaxed text-mist">Como conduzimos projetos, processos e capacitação com foco em resultado.</p>
                <ul class="mt-8 flex flex-1 flex-col gap-6">
                    @foreach($expertise['managerial'] as $item)
                        <li>
                            <div class="flex items-baseline justify-between gap-4">
                                <p class="font-medium text-snow">{{ $item['title'] }}</p>
                                <span class="shrink-0 text-sm font-semibold tabular-nums text-brand-bright">{{ $item['level'] }}%</span>
                            </div>
                            <p class="mt-1 text-xs text-mist sm:text-sm">{{ $item['description'] }}</p>
                            <div class="skill-bar mt-3" aria-hidden="true">
                                <span style="width: {{ $item['level'] }}%"></span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="site-card flex h-full flex-col p-6 sm:p-8">
                <h3 class="font-display text-xl font-semibold">Tecnologias e ferramentas</h3>
                <p class="mt-2 text-sm leading-relaxed text-mist">Linguagens, plataformas e ferramentas usadas na entrega do dia a dia.</p>
                <div class="mt-8 flex flex-1 flex-col gap-6">
                    @foreach($expertise['technical'] as $group)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $group['group'] }}</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach($group['items'] as $tool)
                                    <span class="rounded-sm border border-line px-3 py-1 text-xs text-snow sm:text-sm">{{ $tool }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="mt-10">
            <h3 class="font-display text-xl font-semibold">Indicadores e dados</h3>
            <p class="mt-2 max-w-3xl text-sm leading-relaxed text-mist">Do dado operacional ao número que a gestão usa — Power BI, importações e plataformas no contexto educacional.</p>
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($expertise['bi'] as $item)
                    <article class="site-card p-5">
                        <h4 class="font-display text-lg font-semibold text-snow">{{ $item['title'] }}</h4>
                        <p class="mt-2 text-sm leading-relaxed text-mist">{{ $item['description'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </div>
</section>
