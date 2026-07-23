@php
    $team = $team ?? [];
    $people = $team['people'] ?? [];
    $intro = $team['intro'] ?? null;
@endphp

<section id="equipe" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <p class="section-kicker">Quem é quem</p>
            <h2 class="section-title">Pessoas por trás da entrega</h2>
            @if($intro)
                <p class="mt-4 text-sm text-mist sm:text-base">{{ $intro }}</p>
            @endif
        </div>

        <div class="mt-12 space-y-8">
            @foreach($people as $index => $person)
                @php
                    $featured = (bool) ($person['featured'] ?? false);
                    $links = $person['links'] ?? [];
                    $focus = $person['focus'] ?? [];
                    $career = $person['career'] ?? null;
                    $technologies = $person['technologies'] ?? null;
                    $techItems = collect($technologies['items'] ?? [])->groupBy('level');
                    $levelMeta = $technologies['levels'] ?? [
                        'direct' => ['label' => 'Direta', 'hint' => ''],
                        'related' => ['label' => 'Relacionada', 'hint' => ''],
                        'indirect' => ['label' => 'Indireta', 'hint' => ''],
                        'operational' => ['label' => 'Operacional', 'hint' => ''],
                    ];
                    $modalId = 'career-modal-'.$index;
                @endphp

                <article
                    @class([
                        'border border-line bg-panel/80',
                        'rounded-sm' => true,
                    ])
                >
                    <div @class([
                        'grid gap-0',
                        'lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]' => $featured,
                    ])>
                        <div @class([
                            'relative flex flex-col justify-between border-b border-line bg-[linear-gradient(160deg,rgba(26,95,158,0.16),rgba(199,70,52,0.10),transparent_60%)] p-6 sm:p-8',
                            'lg:border-b-0 lg:border-r' => $featured,
                            'min-h-[14rem]' => $featured,
                        ])>
                            <div class="relative">
                                @if(! empty($person['photo']))
                                    <img
                                        src="{{ asset($person['photo']) }}"
                                        alt="{{ $person['name'] }}"
                                        class="h-28 w-28 rounded-sm object-cover ring-1 ring-line sm:h-32 sm:w-32"
                                    >
                                @else
                                    <div class="inline-flex h-28 w-28 items-center justify-center rounded-sm border border-brand/40 bg-ink/40 font-display text-3xl font-bold tracking-wide text-brand-bright sm:h-32 sm:w-32 sm:text-4xl">
                                        {{ $person['initials'] ?? collect(explode(' ', $person['name']))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}
                                    </div>
                                @endif

                                <h3 class="mt-6 font-display text-2xl font-bold text-snow sm:text-3xl">{{ $person['name'] }}</h3>
                                <p class="mt-2 text-sm font-medium text-brand-bright sm:text-base">{{ $person['role'] }}</p>
                            </div>

                            @if($links)
                                <div class="relative mt-8">
                                    <x-site.contact-icons
                                        :linkedin="$links['linkedin'] ?? null"
                                        :github="$links['github'] ?? null"
                                        :telegram="$links['telegram'] ?? null"
                                        :telegram-handle="! empty($links['telegram']) ? '@'.ltrim(parse_url($links['telegram'], PHP_URL_PATH) ?: 'Telegram', '/') : null"
                                        :site="$links['site'] ?? null"
                                    />
                                </div>
                            @endif
                        </div>

                        <div class="p-6 sm:p-8 lg:p-10">
                            <p class="text-sm leading-relaxed text-mist sm:text-base">{{ $person['bio'] }}</p>

                            @if($focus)
                                <div class="mt-8">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Foco de atuação</p>
                                    <ul class="mt-4 grid gap-3 sm:grid-cols-2">
                                        @foreach($focus as $item)
                                            <li class="flex items-start gap-2.5 text-sm text-snow">
                                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-bright"></span>
                                                <span>{{ $item }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @php $practice = $career['practice'] ?? null; @endphp
                            @if(! empty($practice['pillars']))
                                <div class="mt-8 border-t border-line pt-8">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">
                                        {{ $practice['title'] ?? 'Como o resultado é produzido' }}
                                    </p>
                                    @if(! empty($practice['intro']))
                                        <p class="mt-2 text-sm text-mist">{{ $practice['intro'] }}</p>
                                    @endif
                                    <ul class="mt-5 space-y-4">
                                        @foreach(array_slice($practice['pillars'], 0, 3) as $pillar)
                                            <li>
                                                <p class="text-sm font-semibold text-snow">{{ $pillar['title'] }}</p>
                                                <p class="mt-1 text-sm leading-relaxed text-mist">{{ $pillar['body'] }}</p>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if(count($practice['pillars']) > 3 && $career)
                                        <p class="mt-4 text-xs text-mist">
                                            Processo completo, versionamento, testes e postura — em
                                            <button type="button" class="font-semibold text-brand-bright underline-offset-2 hover:underline" data-dialog-open="{{ $modalId }}">
                                                Ver trajetória completa
                                            </button>.
                                        </p>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-8 flex flex-wrap gap-3">
                                @if($career)
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center rounded-sm bg-brand px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-bright"
                                        data-dialog-open="{{ $modalId }}"
                                        aria-haspopup="dialog"
                                        aria-controls="{{ $modalId }}"
                                    >
                                        Ver trajetória completa
                                    </button>
                                @endif
                                <a href="#contato" class="inline-flex items-center justify-center rounded-sm border border-line px-5 py-2.5 text-sm font-semibold text-snow transition hover:border-brand-bright/50">
                                    Falar com {{ explode(' ', $person['name'])[0] }}
                                </a>
                                @if(! empty($links['linkedin']))
                                    <a
                                        href="{{ $links['linkedin'] }}"
                                        target="_blank"
                                        rel="noopener"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line text-brand-bright transition hover:border-brand-bright/50 hover:text-snow"
                                        aria-label="Abrir LinkedIn"
                                        title="LinkedIn"
                                    >
                                        <x-ui.icon name="linkedin" class="h-5 w-5" />
                                    </a>
                                @endif
                            </div>

                            @if(! empty($techItems))
                                <div class="mt-10 border-t border-line pt-8">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">
                                        {{ $technologies['title'] ?? 'Tecnologias e operação' }}
                                    </p>
                                    @if(! empty($technologies['intro']))
                                        <p class="mt-2 text-sm text-mist">{{ $technologies['intro'] }}</p>
                                    @endif

                                    <ul class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs text-mist" aria-label="Legenda de experiência">
                                        @foreach($levelMeta as $levelKey => $meta)
                                            <li class="inline-flex items-center gap-2">
                                                <span @class([
                                                    'h-2 w-2 shrink-0 rounded-full',
                                                    'bg-brand-bright' => $levelKey === 'direct',
                                                    'bg-snow' => $levelKey === 'related',
                                                    'bg-mist/70' => $levelKey === 'indirect',
                                                    'bg-emerald-500' => $levelKey === 'operational',
                                                ])></span>
                                                <span>
                                                    <span class="font-medium text-snow">{{ $meta['label'] }}</span>
                                                    @if(! empty($meta['hint']))
                                                        <span class="hidden sm:inline"> — {{ $meta['hint'] }}</span>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    <div class="mt-6 space-y-6">
                                        @foreach($levelMeta as $levelKey => $meta)
                                            @php $group = $techItems->get($levelKey, collect()); @endphp
                                            @continue($group->isEmpty())

                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-mist">
                                                    {{ $meta['label'] }}
                                                </p>
                                                <ul class="mt-3 grid grid-cols-3 gap-2 xs:grid-cols-4 sm:grid-cols-5 md:grid-cols-4 lg:grid-cols-5">
                                                    @foreach($group as $tech)
                                                        @php
                                                            $logoPath = $tech['logo'] ?? null;
                                                            $icon = $tech['icon'] ?? null;
                                                            if ($logoPath) {
                                                                $logoUrl = str_starts_with($logoPath, 'http')
                                                                    ? $logoPath
                                                                    : asset($logoPath);
                                                                $invertLogo = (bool) ($tech['invert'] ?? false);
                                                            } elseif ($icon) {
                                                                $logoUrl = 'https://cdn.jsdelivr.net/npm/simple-icons@14/icons/'.$icon.'.svg';
                                                                $invertLogo = (bool) ($tech['invert'] ?? true);
                                                            } else {
                                                                $logoUrl = null;
                                                                $invertLogo = false;
                                                            }
                                                            $initials = collect(preg_split('/[\s\-_]+/', $tech['name']))
                                                                ->filter()
                                                                ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                                                                ->take(2)
                                                                ->implode('');
                                                        @endphp
                                                        <li>
                                                            <div
                                                                class="group flex h-full flex-col items-center gap-2 rounded-sm border border-line bg-ink/30 px-2 py-3 text-center transition hover:border-brand-bright/40"
                                                                title="{{ $tech['name'] }} · {{ $meta['label'] }}{{ ! empty($tech['kind']) ? ' · '.$tech['kind'] : '' }}"
                                                            >
                                                                <span @class([
                                                                    'flex h-9 w-9 items-center justify-center rounded-sm',
                                                                    'ring-1 ring-brand-bright/50' => $levelKey === 'direct',
                                                                    'ring-1 ring-line' => $levelKey !== 'direct',
                                                                ])>
                                                                    @if($logoUrl)
                                                                        <img
                                                                            src="{{ $logoUrl }}"
                                                                            alt=""
                                                                            width="28"
                                                                            height="28"
                                                                            loading="lazy"
                                                                            decoding="async"
                                                                            @class([
                                                                                'h-7 w-7 object-contain opacity-90',
                                                                                'dark:invert' => $invertLogo,
                                                                            ])
                                                                        >
                                                                    @else
                                                                        <span class="font-display text-[10px] font-bold tracking-wide text-brand-bright">{{ $initials }}</span>
                                                                    @endif
                                                                </span>
                                                                <span class="line-clamp-2 text-[11px] leading-tight text-snow">{{ $tech['name'] }}</span>
                                                                @if(! empty($tech['kind']))
                                                                    <span class="sr-only">{{ $tech['kind'] }}</span>
                                                                @endif
                                                            </div>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($career)
                        <div
                            id="{{ $modalId }}"
                            class="career-dialog"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="{{ $modalId }}-title"
                            hidden
                        >
                            <button
                                type="button"
                                class="career-dialog__backdrop"
                                data-dialog-close
                                aria-label="Fechar trajetória"
                            ></button>
                            <div class="career-dialog__panel" role="document">
                                <div class="flex max-h-[90svh] flex-col">
                                    <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-line bg-panel px-5 py-4 sm:px-6">
                                        <div class="min-w-0 pr-2">
                                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Currículo resumido</p>
                                            <h4 id="{{ $modalId }}-title" class="mt-1 font-display text-xl font-bold text-snow sm:text-2xl">
                                                {{ $person['name'] }}
                                            </h4>
                                            <p class="mt-1 text-sm text-mist">{{ $career['headline'] ?? 'Trajetória profissional' }}</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-sm border border-line text-lg leading-none text-snow transition hover:border-brand-bright/50 hover:text-brand-bright"
                                            data-dialog-close
                                            aria-label="Fechar trajetória"
                                        >
                                            <span aria-hidden="true">✕</span>
                                        </button>
                                    </div>

                                    <div class="space-y-8 overflow-y-auto px-5 py-6 sm:px-6">
                                        @if(! empty($career['summary']))
                                            <p class="text-sm leading-relaxed text-mist sm:text-base">{{ $career['summary'] }}</p>
                                        @endif

                                        @if(! empty($career['attractors']))
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">O que chama atenção ao contratante</p>
                                                <ul class="mt-4 space-y-3">
                                                    @foreach($career['attractors'] as $point)
                                                        <li class="flex gap-3 text-sm text-snow">
                                                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-bright"></span>
                                                            <span>{{ $point }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        @php $practice = $career['practice'] ?? null; @endphp
                                        @if(! empty($practice['pillars']))
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">
                                                    {{ $practice['title'] ?? 'Como o resultado é produzido' }}
                                                </p>
                                                @if(! empty($practice['intro']))
                                                    <p class="mt-2 text-sm text-mist">{{ $practice['intro'] }}</p>
                                                @endif
                                                <ol class="mt-5 space-y-5">
                                                    @foreach($practice['pillars'] as $pillar)
                                                        <li class="border-l-2 border-brand/40 pl-4">
                                                            <p class="text-sm font-semibold text-snow">{{ $pillar['title'] }}</p>
                                                            <p class="mt-1.5 text-sm leading-relaxed text-mist">{{ $pillar['body'] }}</p>
                                                        </li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endif

                                        @if(! empty($career['timeline']))
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Linha do tempo</p>
                                                <ol class="mt-4 space-y-4 border-l border-line pl-5">
                                                    @foreach($career['timeline'] as $step)
                                                        <li class="relative">
                                                            <span class="absolute -left-[1.41rem] top-1.5 h-2.5 w-2.5 rounded-full border border-brand bg-panel"></span>
                                                            <p class="text-xs font-semibold uppercase tracking-wide text-brand-bright">{{ $step['period'] }}</p>
                                                            <p class="mt-1 font-medium text-snow">{{ $step['title'] }}</p>
                                                            <p class="mt-1 text-sm text-mist">{{ $step['detail'] }}</p>
                                                        </li>
                                                    @endforeach
                                                </ol>
                                            </div>
                                        @endif

                                        @if(! empty($career['stack_spotlight']))
                                            <div>
                                                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Stack em evidência</p>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    @foreach($career['stack_spotlight'] as $skill)
                                                        <span class="rounded-sm border border-line px-2.5 py-1 text-xs text-snow">{{ $skill }}</span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="flex flex-wrap gap-3 border-t border-line pt-5">
                                            <a href="#contato" class="inline-flex items-center justify-center rounded-sm bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-bright" data-dialog-close>
                                                Solicitar proposta
                                            </a>
                                            @if(! empty($links['linkedin']))
                                                <a
                                                    href="{{ $links['linkedin'] }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex h-10 w-10 items-center justify-center rounded-sm border border-line text-brand-bright transition hover:border-brand-bright/50 hover:text-snow"
                                                    aria-label="Abrir LinkedIn"
                                                    title="LinkedIn"
                                                    data-dialog-close
                                                >
                                                    <x-ui.icon name="linkedin" class="h-5 w-5" />
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
</section>
