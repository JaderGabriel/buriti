@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Projetos</h1>
            <p class="mt-1 text-mist">Cadastro com links, GitHub, logo e contrato</p>
        </div>
        <x-ui.button :href="route('admin.projects.create')">Novo projeto</x-ui.button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($projects as $project)
            <article class="rounded-2xl border border-line bg-panel p-5">
                <div class="flex items-start gap-3">
                    @if($project->logoUrl())
                        <img src="{{ $project->logoUrl() }}" alt="" class="h-12 w-12 rounded-xl object-cover">
                    @else
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl border border-line text-brand-bright">
                            {{ strtoupper(substr($project->name, 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h2 class="truncate font-display text-lg font-semibold">{{ $project->name }}</h2>
                        <p class="text-xs uppercase tracking-wide text-mist">
                            {{ $project->status->label() }}{{ $project->is_public ? ' · público' : '' }}
                        </p>
                    </div>
                </div>
                <p class="mt-3 line-clamp-3 text-sm text-mist">{{ $project->information }}</p>
                <div class="mt-4 flex flex-wrap gap-3 text-sm">
                    @if($project->website_url)
                        <a href="{{ $project->website_url }}" target="_blank" rel="noopener" class="text-brand-bright">Site</a>
                    @endif
                    @if($project->github_url)
                        <a href="{{ $project->github_url }}" target="_blank" rel="noopener" class="text-brand-bright">GitHub</a>
                    @endif
                    @if($project->contractUrl())
                        <a href="{{ $project->contractUrl() }}" target="_blank" rel="noopener" class="text-brand-bright">Contrato</a>
                    @endif
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <a href="{{ route('admin.projects.edit', $project) }}" class="rounded-full border border-line px-4 py-1.5 text-sm hover:border-brand-bright/50">Editar</a>
                    <form method="POST" action="{{ route('admin.projects.destroy', $project) }}" data-confirm="Remover este projeto?">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-full border border-red-500/30 px-4 py-1.5 text-sm text-red-300">Excluir</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="text-mist">Nenhum projeto cadastrado.</p>
        @endforelse
    </div>

    <div class="mt-6">{{ $projects->links() }}</div>
@endsection
