@extends('layouts.admin')

@section('content')
<div class="crm-workspace">
    <div class="crm-workspace__header">
        <div>
            <p class="crm-workspace__eyebrow">CRM comercial</p>
            <h1 class="crm-workspace__title">Empresas</h1>
            <p class="crm-workspace__lead">Contas cliente com contatos, projetos e oportunidades agregados numa ficha.</p>
        </div>
        <div class="crm-workspace__actions">
            <a href="{{ route('admin.contacts.index') }}" class="pm-btn pm-btn--ghost">
                <x-ui.icon name="contact" class="h-4 w-4" />
                Contatos
            </a>
            <a href="{{ route('admin.companies.create') }}" class="pm-btn pm-btn--primary">
                <x-ui.icon name="company" class="h-4 w-4" />
                Nova empresa
            </a>
        </div>
    </div>

    <x-admin.crm-journey current="contact" class="mb-5" />

    <div class="crm-status-strip mb-5">
        @foreach([
            \App\Enums\CompanyStatus::Active,
            \App\Enums\CompanyStatus::Inactive,
        ] as $status)
            <a
                href="{{ route('admin.companies.index', ['status' => $status->value, 'q' => request('q')]) }}"
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
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar nome, CNPJ, e-mail…" class="min-w-[14rem] flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
        <select name="status" class="rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
            <option value="">Todos os status</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Filtrar</button>
    </form>

    @if($companies->isEmpty())
        <div class="rounded-sm border border-dashed border-line px-6 py-16 text-center text-mist">
            Nenhuma empresa ainda. Crie a primeira ou aguarde contatos com empresa no formulário do site.
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($companies as $company)
                <a href="{{ route('admin.companies.show', $company) }}" class="rounded-sm border border-line bg-panel p-5 transition hover:border-brand-bright/40 hover:bg-ink/30">
                    <div class="flex items-start justify-between gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-sm bg-brand/15 text-sm font-semibold text-brand-bright">
                            {{ $company->initials() ?: '?' }}
                        </span>
                        <x-admin.crm-badge :status="$company->status" compact />
                    </div>
                    <h2 class="mt-4 font-display text-lg font-semibold">{{ $company->displayName() }}</h2>
                    @if($company->trade_name && $company->trade_name !== $company->name)
                        <p class="text-sm text-mist">{{ $company->name }}</p>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-3 text-xs text-mist">
                        <span>{{ $company->contacts_count }} contato{{ $company->contacts_count === 1 ? '' : 's' }}</span>
                        <span>·</span>
                        <span>{{ $company->projects_count }} projeto{{ $company->projects_count === 1 ? '' : 's' }}</span>
                        <span>·</span>
                        <span>{{ $company->opportunities_count }} oportunidade{{ $company->opportunities_count === 1 ? '' : 's' }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-8">{{ $companies->links() }}</div>
</div>
@endsection
