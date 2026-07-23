@extends('layouts.admin')

@section('content')
    @php
        $editing = $project->exists;
        $currentStatus = old('status', $project->status?->value ?? 'active');
    @endphp
    <div class="mb-8">
        <a href="{{ route('admin.projects.index') }}" class="text-sm text-mist hover:text-snow">← Projetos</a>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $editing ? 'Editar projeto' : 'Novo projeto' }}</h1>
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}"
          enctype="multipart/form-data"
          class="max-w-3xl space-y-4 rounded-2xl border border-line bg-panel p-5 sm:p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <x-ui.input name="name" label="Nome" :value="old('name', $project->name)" required />
        <x-ui.input type="textarea" name="information" label="Informações" :value="old('information', $project->information)" rows="5" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input name="category" label="Categoria" :value="old('category', $project->category)" placeholder="BI & Painéis" />
            <x-ui.input name="stack" label="Stack (separada por vírgulas)" :value="old('stack', is_array($project->stack) ? implode(', ', $project->stack) : $project->stack)" placeholder="Laravel, PHP, Power BI" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input type="url" name="website_url" label="Link do site (público)" :value="old('website_url', $project->website_url)" />
            <x-ui.input type="url" name="github_url" label="GitHub (interno se repo privado)" :value="old('github_url', $project->github_url)" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-mist">Status</span>
                <select name="status" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.input type="number" name="sort_order" label="Ordem no portfólio" :value="old('sort_order', $project->sort_order ?? 0)" min="0" />
        </div>

        <div class="rounded-2xl border border-line bg-ink/40 p-4 space-y-3">
            <p class="text-sm font-semibold text-snow">Visibilidade no portfólio</p>
            <label class="flex items-start gap-2 text-sm text-mist">
                <input type="checkbox" name="is_public" value="1" class="mt-1" @checked(old('is_public', $project->is_public))>
                <span>
                    <strong class="text-snow">Exibir no site</strong>
                    <span class="block text-xs">Aparece na seção Portfólio da landing.</span>
                </span>
            </label>
            <label class="flex items-start gap-2 text-sm text-mist">
                <input type="checkbox" name="repo_is_private" value="1" class="mt-1" @checked(old('repo_is_private', $project->repo_is_private))>
                <span>
                    <strong class="text-snow">Repositório privado</strong>
                    <span class="block text-xs">Mostra nome, stack e descrição — sem links de Site/GitHub no site público.</span>
                </span>
            </label>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-mist">Logo</span>
                <input type="file" name="logo" accept="image/*" class="mt-1.5 w-full text-mist file:mr-3 file:rounded-full file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:text-white">
                @if($project->logoUrl())
                    <img src="{{ $project->logoUrl() }}" alt="" class="mt-3 h-16 w-16 rounded-xl object-cover">
                @endif
            </label>
            <label class="block text-sm">
                <span class="text-mist">Contrato (PDF/DOC/imagem)</span>
                <input type="file" name="contract" accept=".pdf,.doc,.docx,image/*" class="mt-1.5 w-full text-mist file:mr-3 file:rounded-full file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:text-white">
                @if($project->contractUrl())
                    <a href="{{ $project->contractUrl() }}" target="_blank" class="mt-3 inline-block text-sm text-brand-bright">Abrir contrato atual</a>
                @endif
            </label>
        </div>

        <x-ui.button type="submit">{{ $editing ? 'Salvar alterações' : 'Cadastrar projeto' }}</x-ui.button>
    </form>

    @if($editing)
        <div class="mt-6 max-w-3xl">
            <x-admin.attachments-panel
                :attachable="$project"
                type="projects"
                :kinds="['document', 'media', 'photo']"
                layout="folder"
                title="Pasta do projeto"
                description="Documentos, mídias e fotos internas. Não alteram o portfólio público da home."
                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*,video/*,audio/*"
            />
        </div>
    @endif
@endsection
