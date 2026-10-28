@extends('layouts.admin')

@section('content')
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">Entrega · Depoimentos</p>
            <h1 class="crm-workspace__title">Convidar cliente</h1>
            <p class="crm-workspace__lead">Gera um link unlisted. O cliente escreve estrelas e comentário; o original fica selado e não pode ser alterado.</p>
        </div>
        <a href="{{ route('admin.testimonials.index') }}" class="pm-btn pm-btn--ghost">← Lista</a>
    </div>

    <form method="POST" action="{{ route('admin.testimonials.store') }}" class="max-w-xl space-y-4 rounded-sm border border-line bg-panel p-5">
        @csrf
        <label class="block text-sm">
            <span class="mb-1 block text-mist">Contacto</span>
            <select name="contact_id" required class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                <option value="">Escolher…</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}" @selected((int) old('contact_id', $selectedContactId) === $contact->id)>
                        {{ $contact->name }}@if($contact->email) — {{ $contact->email }}@endif
                    </option>
                @endforeach
            </select>
            @error('contact_id') <span class="mt-1 block text-xs text-red-300">{{ $message }}</span> @enderror
        </label>

        <label class="block text-sm">
            <span class="mb-1 block text-mist">Projeto (opcional — pode vincular depois)</span>
            <select name="project_id" class="w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                <option value="">Ainda sem projeto</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected((int) old('project_id', $selectedProjectId) === $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <button type="submit" class="pm-btn pm-btn--primary">
            <x-ui.icon name="mail" class="h-4 w-4" />
            Gerar link de convite
        </button>
    </form>
</div>
@endsection
