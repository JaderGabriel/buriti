@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">CRM</p>
            <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">Contatos</h1>
            <p class="mt-1 text-mist">Mural de pessoas — leads, clientes e histórico</p>
        </div>
        <x-ui.button :href="route('admin.contacts.create')">Novo contato</x-ui.button>
    </div>

    <form method="GET" class="mb-8 flex flex-wrap gap-3">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar nome, e-mail, empresa…" class="min-w-[14rem] flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
        <select name="status" class="rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
            <option value="">Todos os status</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Filtrar</button>
    </form>

    @if($contacts->isEmpty())
        <div class="rounded-sm border border-dashed border-line px-6 py-16 text-center text-mist">
            Nenhum contato ainda. Crie o primeiro ou aguarde uma mensagem do site.
        </div>
    @else
        <div class="postit-board grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach($contacts as $index => $contact)
                @php
                    $tone = match ($contact->status->value) {
                        'active' => 'postit-blue',
                        'inactive' => 'postit-slate',
                        default => 'postit-amber',
                    };
                    $tilt = ['-rotate-1', 'rotate-1', 'rotate-0', '-rotate-2', 'rotate-2'][$index % 5];
                    $initials = collect(preg_split('/\s+/', trim($contact->name)) ?: [])
                        ->filter()
                        ->take(2)
                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                        ->implode('');
                @endphp

                <a
                    href="{{ route('admin.contacts.show', $contact) }}"
                    class="postit {{ $tone }} group relative flex min-h-[13.5rem] flex-col p-5 pt-7 shadow-md transition duration-200 hover:-translate-y-1 hover:shadow-lg {{ $tilt }} hover:rotate-0"
                >
                    <span class="postit-pin" aria-hidden="true"></span>

                    <div class="flex items-start justify-between gap-3">
                        <span class="postit-avatar">{{ $initials ?: '?' }}</span>
                        <span class="postit-badge">{{ $contact->status->label() }}</span>
                    </div>

                    <h2 class="postit-name mt-4 font-script text-2xl leading-tight">{{ $contact->name }}</h2>

                    @if($contact->company)
                        <p class="mt-1 text-sm font-medium opacity-80">{{ $contact->company }}</p>
                    @endif

                    <div class="mt-auto space-y-1 pt-4 text-xs opacity-75">
                        <p class="truncate">{{ $contact->email ?? 'Sem e-mail' }}</p>
                        <p class="truncate">{{ $contact->phone ?? 'Sem telefone' }}</p>
                        <p class="pt-1 uppercase tracking-[0.12em]">
                            {{ $contact->source->label() }}
                            @if($contact->preferred_channel)
                                · {{ $contact->preferred_channel }}
                            @endif
                        </p>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-8">{{ $contacts->links() }}</div>
@endsection
