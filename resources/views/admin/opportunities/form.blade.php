@extends('layouts.admin')

@section('content')
    @php
        $editing = $opportunity->exists;
        $currentStage = old('stage', $opportunity->stage?->value ?? 'lead');
    @endphp

    <div class="crm-workspace">
        <div class="mb-4">
            <a href="{{ route('admin.opportunities.index') }}" class="text-sm text-mist hover:text-snow">← Pipeline</a>
            <p class="crm-workspace__eyebrow mt-3">{{ $editing ? 'Ficha da oportunidade' : 'Nova oportunidade' }}</p>
            <h1 class="crm-workspace__title">{{ $editing ? $opportunity->title : 'Abrir negócio' }}</h1>
            <p class="crm-workspace__lead">Avance o estágio no funil Lead → Contrato e registre valor e previsão.</p>
        </div>

        <x-admin.crm-journey current="opportunity" class="mb-5" />

        <form method="POST"
              action="{{ $editing ? route('admin.opportunities.update', $opportunity) : route('admin.opportunities.store') }}"
              class="space-y-5">
            @csrf
            @if($editing) @method('PUT') @endif

            <section class="pm-panel">
                <header class="pm-panel__head">
                    <h2>Estágio no funil</h2>
                    <p>Escolha a etapa atual — cores e ícones seguem o CRM</p>
                </header>
                <div
                    class="crm-stage-picker"
                    role="radiogroup"
                    aria-label="Estágio"
                    x-data="{ stage: @js($currentStage) }"
                >
                    @foreach($stageMeta as $meta)
                        <label
                            @class(['crm-stage-picker__option', 'crm-stage-picker__option--'.$meta['tone']])
                            :class="{ 'is-selected': stage === @js($meta['value']) }"
                        >
                            <input
                                type="radio"
                                name="stage"
                                value="{{ $meta['value'] }}"
                                x-model="stage"
                                required
                            >
                            <span class="crm-stage-picker__icon"><x-ui.icon :name="$meta['icon']" class="h-4 w-4" /></span>
                            <span class="crm-stage-picker__label">{{ $meta['label'] }}</span>
                            <span class="crm-stage-picker__desc">{{ $meta['description'] }}</span>
                        </label>
                    @endforeach
                </div>
                @error('stage') <p class="mt-2 text-xs text-red-400">{{ $message }}</p> @enderror
            </section>

            <section class="pm-panel max-w-3xl space-y-4">
                <header class="pm-panel__head">
                    <h2>Dados do negócio</h2>
                    <p>Contato, título e valor estimado</p>
                </header>

                <label class="block text-sm">
                    <span class="text-mist">Contato</span>
                    <select name="contact_id" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                        <option value="">Selecione…</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}" @selected((string) old('contact_id', $opportunity->contact_id) === (string) $contact->id)>
                                {{ $contact->name }}@if($contact->companyLabel()) ({{ $contact->companyLabel() }})@endif
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
                    <x-ui.input type="number" step="0.01" name="value" label="Valor (R$)" :value="old('value', $opportunity->value)" />
                    <x-ui.input type="date" name="expected_close_at" label="Previsão de fechamento" :value="old('expected_close_at', optional($opportunity->expected_close_at)->format('Y-m-d'))" />
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

                <div class="flex flex-wrap gap-2 pt-2">
                    <a href="{{ route('admin.opportunities.index') }}" class="pm-btn pm-btn--ghost">Cancelar</a>
                    <button type="submit" class="pm-btn pm-btn--primary">{{ $editing ? 'Salvar' : 'Criar oportunidade' }}</button>
                </div>
            </section>
        </form>

        @if($editing)
            <div class="mt-6 max-w-3xl">
                <x-admin.attachments-panel
                    :attachable="$opportunity"
                    type="opportunities"
                    :kinds="['document']"
                    layout="folder"
                    title="Pasta de arquivos"
                    description="Propostas, contratos e anexos desta oportunidade."
                />
            </div>

            <form method="POST" action="{{ route('admin.opportunities.destroy', $opportunity) }}" class="mt-4 max-w-3xl" data-confirm="Remover oportunidade?">
                @csrf
                @method('DELETE')
                <button type="submit" class="rounded-sm border border-red-500/40 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">Remover</button>
            </form>
        @endif
    </div>
@endsection
