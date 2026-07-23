@extends('layouts.admin')

@section('content')
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">CRM comercial</p>
            <h1 class="crm-workspace__title">Contatos</h1>
            <p class="crm-workspace__lead">Pessoas no funil — do lead ao cliente ativo, com status colorido e rastreável.</p>
        </div>
        <div class="crm-workspace__actions">
            <a href="{{ route('admin.companies.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="company" class="h-4 w-4" />
                Empresas
            </a>
            <a href="{{ route('admin.opportunities.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="opportunity" class="h-4 w-4" />
                Pipeline
            </a>
            <a href="{{ route('admin.contacts.create') }}" class="pm-btn pm-btn--primary">
                <x-ui.icon name="contact" class="h-4 w-4" />
                Novo contato
            </a>
        </div>
    </div>

    <x-admin.crm-journey current="contact" class="mb-5" />

    <div class="crm-status-strip mb-5">
        @foreach([
            \App\Enums\ContactStatus::Lead,
            \App\Enums\ContactStatus::Active,
            \App\Enums\ContactStatus::Inactive,
        ] as $status)
            <a
                href="{{ route('admin.contacts.index', ['status' => $status->value, 'q' => request('q')]) }}"
                @class(['crm-status-strip__item', 'crm-status-strip__item--'.$status->tone(), 'is-active' => request('status') === $status->value])
            >
                <span class="crm-status-strip__icon"><x-ui.icon :name="$status->icon()" class="h-4 w-4" /></span>
                <span>
                    <strong>{{ $status->label() }}</strong>
                    <small>{{ $status->description() }}</small>
                </span>
                <em>{{ (int) ($statusCounts[$status->value] ?? 0) }}</em>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
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
                        <x-admin.crm-badge :status="$contact->status" compact class="!normal-case" />
                    </div>

                    <h2 class="postit-name mt-4">{{ $contact->name }}</h2>

                    @if($contact->companyLabel())
                        <p class="postit-meta mt-1">{{ $contact->companyLabel() }}</p>
                    @endif

                    <div class="postit-foot mt-auto pt-4">
                        <p class="truncate">{{ $contact->email ?? 'Sem e-mail' }}</p>
                        <p class="truncate">{{ \App\Support\PhoneNumber::format($contact->phone) ?? 'Sem telefone' }}</p>
                        <p class="postit-foot__source">
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
</div>
@endsection
