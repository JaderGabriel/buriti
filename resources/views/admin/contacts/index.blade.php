@extends('layouts.admin')

@section('content')
@php
    $filterQuery = request()->except(['letter', 'page']);
    $viewQuery = array_merge($filterQuery, ['view' => $view]);
@endphp
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">CRM comercial</p>
            <h1 class="crm-workspace__title">Contatos</h1>
            <p class="crm-workspace__lead">Agenda telefónica em ordem alfabética — ligue, mande WhatsApp ou abra a ficha.</p>
        </div>
        <div class="crm-workspace__actions">
            <button type="button" class="pm-btn pm-btn--ghost" data-bulk-activity-open>
                <x-ui.icon name="task" class="h-4 w-4" />
                Registar atividade
            </button>
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
                href="{{ route('admin.contacts.index', array_merge($viewQuery, ['status' => $status->value, 'letter' => null])) }}"
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

    <div class="phonebook-toolbar mb-4">
        <div class="phonebook-views" role="tablist" aria-label="Vista de contatos">
            <a
                href="{{ route('admin.contacts.index', array_merge($filterQuery, ['view' => 'phonebook', 'letter' => $letter])) }}"
                @class(['phonebook-views__btn', 'is-active' => $view === 'phonebook'])
            >Agenda</a>
            <a
                href="{{ route('admin.contacts.index', array_merge($filterQuery, ['view' => 'cards', 'letter' => null])) }}"
                @class(['phonebook-views__btn', 'is-active' => $view === 'cards'])
            >Cartões</a>
        </div>

        <form method="GET" class="phonebook-filters">
            <input type="hidden" name="view" value="{{ $view }}">
            @if($letter)
                <input type="hidden" name="letter" value="{{ $letter }}">
            @endif
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar nome, telefone, e-mail…" class="phonebook-filters__search">
            <select name="status" class="phonebook-filters__select">
                <option value="">Status</option>
                @foreach($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="company_id" class="phonebook-filters__select">
                <option value="">Empresa</option>
                @foreach($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>
                        {{ $company->displayName() }}
                    </option>
                @endforeach
            </select>
            <select name="channel" class="phonebook-filters__select">
                <option value="">Canal</option>
                @foreach($channels as $value => $label)
                    <option value="{{ $value }}" @selected(request('channel') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <select name="phone" class="phonebook-filters__select">
                <option value="">Telefone</option>
                <option value="with" @selected(request('phone') === 'with')>Com telefone</option>
                <option value="without" @selected(request('phone') === 'without')>Sem telefone</option>
            </select>
            <button type="submit" class="phonebook-filters__submit">Filtrar</button>
            @if(request()->hasAny(['q', 'status', 'company_id', 'channel', 'phone', 'letter']))
                <a href="{{ route('admin.contacts.index', ['view' => $view]) }}" class="phonebook-filters__clear">Limpar</a>
            @endif
        </form>
    </div>

    <nav class="phonebook-alphabet" aria-label="Índice alfabético">
        <a
            href="{{ route('admin.contacts.index', array_merge($filterQuery, ['letter' => null])) }}"
            @class(['phonebook-alphabet__item', 'is-active' => $letter === null])
        >Todos</a>
        @foreach($alphabet as $char)
            @php $count = (int) ($letterCounts[$char] ?? 0); @endphp
            @if($count > 0)
                <a
                    href="{{ route('admin.contacts.index', array_merge($filterQuery, ['view' => 'phonebook', 'letter' => $char])) }}"
                    @class(['phonebook-alphabet__item', 'is-active' => $letter === $char])
                    title="{{ $count }} contato{{ $count === 1 ? '' : 's' }}"
                >{{ $char }}<span>{{ $count }}</span></a>
            @else
                <span class="phonebook-alphabet__item is-empty" aria-disabled="true">{{ $char }}</span>
            @endif
        @endforeach
    </nav>

    @if($view === 'phonebook')
        @if($contacts->isEmpty())
            <div class="mt-6 rounded-sm border border-dashed border-line px-6 py-16 text-center text-mist">
                Nenhum contato nesta seleção.
            </div>
        @else
            <div class="phonebook mt-5" data-phonebook-select>
                <div class="phonebook-selection" data-phonebook-selection hidden>
                    <span data-phonebook-selection-count>0 selecionados</span>
                    <button type="button" class="pm-btn pm-btn--primary" data-bulk-activity-open data-bulk-activity-from-selection>
                        <x-ui.icon name="task" class="h-4 w-4" />
                        Registar atividade nos selecionados
                    </button>
                    <button type="button" class="phonebook-selection__clear" data-phonebook-selection-clear>Limpar seleção</button>
                </div>
                @foreach($groups as $groupLetter => $items)
                    <section class="phonebook__group" id="letra-{{ $groupLetter === '#' ? 'outros' : $groupLetter }}">
                        <header class="phonebook__header">
                            <h2>{{ $groupLetter }}</h2>
                            <span>{{ $items->count() }}</span>
                        </header>
                        <ul class="phonebook__list">
                            @foreach($items as $contact)
                                @php
                                    $phoneLabel = \App\Support\PhoneNumber::format($contact->phone);
                                    $tel = $contact->telUrl();
                                    $wa = $contact->whatsappUrl();
                                    $initials = collect(preg_split('/\s+/', trim($contact->name)) ?: [])
                                        ->filter()
                                        ->take(2)
                                        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                                        ->implode('');
                                @endphp
                                <li class="phonebook__row">
                                    <label class="phonebook__select" title="Selecionar para atividade">
                                        <input type="checkbox" value="{{ $contact->id }}" data-phonebook-pick>
                                        <span class="sr-only">Selecionar {{ $contact->name }}</span>
                                    </label>
                                    <a href="{{ route('admin.contacts.show', $contact) }}" class="phonebook__identity">
                                        <span class="phonebook__avatar">{{ $initials ?: '?' }}</span>
                                        <span class="phonebook__meta">
                                            <strong>{{ $contact->name }}</strong>
                                            <small>
                                                {{ $contact->companyLabel() ?? 'Sem empresa' }}
                                                @if($contact->role) · {{ $contact->role }} @endif
                                            </small>
                                        </span>
                                    </a>

                                    <div class="phonebook__phone">
                                        @if($phoneLabel)
                                            <span class="phonebook__number">{{ $phoneLabel }}</span>
                                            <div class="phonebook__actions">
                                                @if($tel)
                                                    <a href="{{ $tel }}" class="phonebook__action" title="Ligar" aria-label="Ligar para {{ $contact->name }}">
                                                        <x-ui.icon name="phone" class="h-4 w-4" />
                                                    </a>
                                                @endif
                                                @if($wa)
                                                    <a href="{{ $wa }}" target="_blank" rel="noopener" class="phonebook__action phonebook__action--wa" title="WhatsApp" aria-label="WhatsApp {{ $contact->name }}">
                                                        <x-ui.icon name="whatsapp" class="h-4 w-4" />
                                                    </a>
                                                @endif
                                                <button
                                                    type="button"
                                                    class="phonebook__action"
                                                    title="Registar atividade"
                                                    aria-label="Registar atividade para {{ $contact->name }}"
                                                    data-bulk-activity-open
                                                    data-bulk-activity-ids="{{ $contact->id }}"
                                                >
                                                    <x-ui.icon name="task" class="h-4 w-4" />
                                                </button>
                                            </div>
                                        @else
                                            <span class="phonebook__empty">Sem telefone</span>
                                            <button
                                                type="button"
                                                class="phonebook__action"
                                                title="Registar atividade"
                                                data-bulk-activity-open
                                                data-bulk-activity-ids="{{ $contact->id }}"
                                            >
                                                <x-ui.icon name="task" class="h-4 w-4" />
                                            </button>
                                        @endif
                                    </div>

                                    <div class="phonebook__side">
                                        @if($contact->email)
                                            <a href="mailto:{{ $contact->email }}" class="phonebook__email" title="{{ $contact->email }}">{{ $contact->email }}</a>
                                        @endif
                                        <x-admin.crm-badge :status="$contact->status" compact />
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    @else
        @if($contacts->isEmpty())
            <div class="mt-6 rounded-sm border border-dashed border-line px-6 py-16 text-center text-mist">
                Nenhum contato ainda. Crie o primeiro ou aguarde uma mensagem do site.
            </div>
        @else
            <div class="postit-board mt-5 grid gap-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
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
            @if(method_exists($contacts, 'links'))
                <div class="mt-8">{{ $contacts->links() }}</div>
            @endif
        @endif
    @endif
</div>

@include('admin.contacts.partials.bulk-activity-dialog', [
    'pickerContacts' => $pickerContacts,
    'activityTypes' => $activityTypes,
    'openTasks' => $openTasks,
])
@endsection
