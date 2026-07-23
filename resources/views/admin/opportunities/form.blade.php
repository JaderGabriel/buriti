@extends('layouts.admin')

@section('content')
    @php $editing = $opportunity->exists; @endphp
    <div class="mb-8">
        <a href="{{ route('admin.opportunities.index') }}" class="text-sm text-mist hover:text-snow">← Oportunidades</a>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $editing ? 'Editar oportunidade' : 'Nova oportunidade' }}</h1>
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.opportunities.update', $opportunity) : route('admin.opportunities.store') }}"
          class="max-w-2xl space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <label class="block text-sm">
            <span class="text-mist">Contato</span>
            <select name="contact_id" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                <option value="">Selecione…</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected((string) old('contact_id', $opportunity->contact_id) === (string) $contact->id)>
                        {{ $contact->name }}@if($contact->company) ({{ $contact->company }})@endif
                    </option>
                @endforeach
            </select>
            @error('contact_id') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
        </label>

        <x-ui.input name="title" label="Título" :value="old('title', $opportunity->title)" required />

        <label class="block text-sm">
            <span class="text-mist">Descrição</span>
            <textarea name="description" rows="4" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">{{ old('description', $opportunity->description) }}</textarea>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-mist">Estágio</span>
                <select name="stage" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    @foreach($stages as $value => $label)
                        <option value="{{ $value }}" @selected(old('stage', $opportunity->stage?->value ?? 'lead') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.input type="number" step="0.01" name="value" label="Valor (R$)" :value="old('value', $opportunity->value)" />
        </div>

        <label class="block text-sm">
            <span class="text-mist">Projeto / produto relacionado</span>
            <select name="project_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                <option value="">—</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((string) old('project_id', $opportunity->project_id) === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <x-ui.input type="date" name="expected_close_at" label="Previsão de fechamento" :value="old('expected_close_at', optional($opportunity->expected_close_at)->format('Y-m-d'))" />

        <x-ui.button type="submit">{{ $editing ? 'Salvar' : 'Criar oportunidade' }}</x-ui.button>
    </form>

    @if($editing)
        <div class="mt-6 max-w-2xl">
            <x-admin.attachments-panel
                :attachable="$opportunity"
                type="opportunities"
                :kinds="['document']"
                layout="folder"
                title="Pasta de arquivos"
                description="Propostas, contratos e anexos desta oportunidade."
            />
        </div>

        <form method="POST" action="{{ route('admin.opportunities.destroy', $opportunity) }}" class="mt-4 max-w-2xl" data-confirm="Remover oportunidade?">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-sm border border-red-500/40 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">Remover</button>
        </form>
    @endif
@endsection
