@extends('layouts.admin')

@section('content')
    @php $editing = $company->exists; @endphp
    <div class="mb-8">
        <a href="{{ route('admin.companies.index') }}" class="text-sm text-mist hover:text-snow">← Empresas</a>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $editing ? 'Editar empresa' : 'Nova empresa' }}</h1>
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.companies.update', $company) : route('admin.companies.store') }}"
          class="max-w-2xl space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <x-ui.input name="name" label="Razão / nome" :value="old('name', $company->name)" required />
        <x-ui.input name="trade_name" label="Nome fantasia" :value="old('trade_name', $company->trade_name)" />
        <x-ui.input name="document" label="CNPJ / documento" :value="old('document', $company->document)" />
        <x-ui.phone-field :value="old('phone', $company->phone)" />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input type="email" name="email" label="E-mail" :value="old('email', $company->email)" />
            <x-ui.input type="url" name="website_url" label="Site" :value="old('website_url', $company->website_url)" />
        </div>

        <label class="block text-sm">
            <span class="text-mist">Status</span>
            <select name="status" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $company->status?->value ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm">
            <span class="text-mist">Notas</span>
            <textarea name="notes" rows="4" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">{{ old('notes', $company->notes) }}</textarea>
        </label>

        <x-ui.button type="submit">{{ $editing ? 'Salvar' : 'Criar empresa' }}</x-ui.button>
    </form>
@endsection
