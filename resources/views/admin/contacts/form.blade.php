@extends('layouts.admin')

@section('content')
    @php $editing = $contact->exists; @endphp
    <div class="mb-8">
        <a href="{{ route('admin.contacts.index') }}" class="text-sm text-mist hover:text-snow">← Contatos</a>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $editing ? 'Editar contato' : 'Novo contato' }}</h1>
    </div>

    <form method="POST"
          action="{{ $editing ? route('admin.contacts.update', $contact) : route('admin.contacts.store') }}"
          class="max-w-2xl space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        <x-ui.input name="name" label="Nome" :value="old('name', $contact->name)" required />
        <x-ui.input type="email" name="email" label="E-mail" :value="old('email', $contact->email)" />
        <x-ui.input name="phone" label="Telefone" :value="old('phone', $contact->phone)" />
        <x-ui.input name="company" label="Empresa" :value="old('company', $contact->company)" />
        <x-ui.input name="role" label="Cargo / papel" :value="old('role', $contact->role)" />

        <label class="block text-sm">
            <span class="text-mist">Canal preferido</span>
            <select name="preferred_channel" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                <option value="">—</option>
                @foreach(['email' => 'E-mail', 'phone' => 'Telefone', 'whatsapp' => 'WhatsApp'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('preferred_channel', $contact->preferred_channel) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>

        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="text-mist">Status</span>
                <select name="status" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    @foreach($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $contact->status?->value ?? 'lead') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="text-mist">Origem</span>
                <select name="source" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    @foreach($sources as $value => $label)
                        <option value="{{ $value }}" @selected(old('source', $contact->source?->value ?? 'manual') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <label class="block text-sm">
            <span class="text-mist">Notas</span>
            <textarea name="notes" rows="4" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">{{ old('notes', $contact->notes) }}</textarea>
        </label>

        <x-ui.button type="submit">{{ $editing ? 'Salvar' : 'Criar contato' }}</x-ui.button>
    </form>
@endsection
