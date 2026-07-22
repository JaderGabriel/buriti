@props(['project'])

<article {{ $attributes->merge(['class' => 'group flex h-full flex-col gap-4 rounded-[1.25rem] border border-line bg-panel/60 p-5 transition hover:border-brand/40 sm:p-6']) }}>
    <div class="flex items-start gap-4">
        @if($project->logoUrl())
            <img src="{{ $project->logoUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-xl object-cover sm:h-14 sm:w-14">
        @else
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-line bg-ink text-brand-bright sm:h-14 sm:w-14">
                <span class="font-display text-lg font-bold">{{ strtoupper(substr($project->name, 0, 1)) }}</span>
            </div>
        @endif
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                @if($project->category)
                    <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand">{{ $project->category }}</p>
                @endif
                @if($project->repo_is_private)
                    <span class="rounded-full border border-line px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-mist">Repo privado</span>
                @endif
            </div>
            <h3 class="font-display text-lg font-semibold sm:text-xl">{{ $project->name }}</h3>
        </div>
    </div>

    <p class="flex-1 text-sm leading-relaxed text-mist">{{ \Illuminate\Support\Str::limit($project->information, 220) }}</p>

    @if(!empty($project->stack))
        <div class="flex flex-wrap gap-2">
            @foreach($project->stack as $tech)
                <span class="rounded-full border border-line px-2.5 py-0.5 text-[11px] text-mist">{{ $tech }}</span>
            @endforeach
        </div>
    @endif

    <div class="mt-auto flex flex-wrap items-center gap-3 text-sm">
        @if($project->exposesPublicLinks())
            @if($project->website_url)
                <a href="{{ $project->website_url }}" target="_blank" rel="noopener" class="text-brand-bright hover:underline">Site</a>
            @endif
            @if($project->github_url)
                <a href="{{ $project->github_url }}" target="_blank" rel="noopener" class="text-brand-bright hover:underline">GitHub</a>
            @endif
        @else
            <span class="inline-flex items-center gap-1.5 text-xs text-mist">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-bright"></span>
                Código sob NDA — sem link público
            </span>
        @endif
    </div>
</article>
