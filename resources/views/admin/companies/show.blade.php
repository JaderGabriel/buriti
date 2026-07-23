@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.companies.index') }}" class="text-sm text-mist hover:text-snow">← Empresas</a>
            <h1 class="crm-detail__title">{{ $company->displayName() }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-mist">
                <x-admin.crm-badge :status="$company->status" />
                @if($company->trade_name && $company->trade_name !== $company->name)
                    <span>· {{ $company->name }}</span>
                @endif
                @if($company->document)
                    <span>· {{ $company->document }}</span>
                @endif
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.contacts.create', ['company_id' => $company->id]) }}" class="rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-bright">Novo contato</a>
            <a href="{{ route('admin.companies.edit', $company) }}" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Editar</a>
            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" data-confirm="Remover esta empresa? Contatos e projetos permanecem, sem vínculo.">
                @csrf
                @method('DELETE')
                <button class="rounded-sm border border-red-500/40 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">Remover</button>
            </form>
        </div>
    </div>

    <x-admin.crm-journey current="contact" class="mb-6" />

    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        <div class="rounded-sm border border-line bg-panel px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-mist">Contatos</p>
            <p class="crm-detail__stat mt-1">{{ $company->contacts_count }}</p>
        </div>
        <div class="rounded-sm border border-line bg-panel px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-mist">Projetos</p>
            <p class="crm-detail__stat mt-1">{{ $company->projects_count }}</p>
        </div>
        <div class="rounded-sm border border-line bg-panel px-4 py-3">
            <p class="text-xs uppercase tracking-wide text-mist">Oportunidades</p>
            <p class="crm-detail__stat mt-1">{{ $company->opportunities_count }}</p>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_1.15fr]">
        <div class="space-y-6">
            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="crm-detail__section-title">Dados da empresa</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-mist">E-mail</dt>
                        <dd class="mt-1">
                            @if($company->email)
                                <a href="mailto:{{ $company->email }}" class="text-brand-bright hover:underline">{{ $company->email }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-mist">Telefone</dt>
                        <dd class="mt-1">{{ \App\Support\PhoneNumber::format($company->phone) ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-mist">Site</dt>
                        <dd class="mt-1">
                            @if($company->website_url)
                                <a href="{{ $company->website_url }}" target="_blank" rel="noopener" class="text-brand-bright hover:underline">{{ $company->website_url }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-mist">Documento</dt>
                        <dd class="mt-1">{{ $company->document ?? '—' }}</dd>
                    </div>
                </dl>
                @if($company->notes)
                    <p class="mt-4 border-t border-line pt-4 text-sm text-mist whitespace-pre-wrap">{{ $company->notes }}</p>
                @endif
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="crm-detail__section-title">Contatos alocados</h2>
                    <a href="{{ route('admin.contacts.create', ['company_id' => $company->id]) }}" class="text-sm text-brand-bright hover:underline">Adicionar</a>
                </div>
                <ul class="space-y-3">
                    @forelse($company->contacts as $contact)
                        <li class="rounded-sm border border-line/70 px-3 py-3">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <a href="{{ route('admin.contacts.show', $contact) }}" class="font-medium text-brand-bright hover:underline">{{ $contact->name }}</a>
                                    <p class="text-xs text-mist">
                                        {{ $contact->role ?? 'Sem cargo' }}
                                        @if($contact->email) · {{ $contact->email }} @endif
                                    </p>
                                </div>
                                <x-admin.crm-badge :status="$contact->status" compact />
                            </div>
                            <p class="mt-2 text-xs text-mist">
                                {{ $contact->opportunities_count }} opp.
                                · {{ $contact->projects_count }} projeto{{ $contact->projects_count === 1 ? '' : 's' }}
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhum contato nesta empresa.</li>
                    @endforelse
                </ul>

                <form method="POST" action="{{ route('admin.companies.contacts.store', $company) }}" class="mt-5 space-y-3 border-t border-line pt-4">
                    @csrf
                    <p class="text-sm font-medium">Contato rápido</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.input name="name" label="Nome" :value="old('name')" required />
                        <x-ui.input type="email" name="email" label="E-mail" :value="old('email')" />
                        <div class="sm:col-span-2">
                            <x-ui.phone-field :value="old('phone')" />
                        </div>
                        <x-ui.input name="role" label="Cargo" :value="old('role')" />
                    </div>
                    <label class="block text-sm">
                        <span class="text-mist">Status</span>
                        <select name="status" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            @foreach($contactStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'lead') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="rounded-sm border border-line px-3 py-2 text-sm hover:border-brand-bright/50">Criar contato</button>
                </form>
            </article>
        </div>

        <div class="space-y-6">
            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="crm-detail__section-title">Oportunidades (via contatos)</h2>
                <p class="mt-1 text-xs text-mist">Podem envolver só alguns contatos da empresa — aqui aparece o conjunto.</p>
                <ul class="mt-4 space-y-3">
                    @forelse($company->opportunities as $opportunity)
                        <li class="rounded-sm border border-line/70 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ $opportunity->title }}</p>
                                <x-admin.crm-badge :stage="$opportunity->stage" compact />
                            </div>
                            <p class="text-xs text-mist">
                                <a href="{{ route('admin.contacts.show', $opportunity->contact) }}" class="text-brand-bright hover:underline">{{ $opportunity->contact?->name }}</a>
                                · {{ $opportunity->project?->name ?? 'Sem projeto' }}
                                @if($opportunity->value) · R$ {{ number_format((float) $opportunity->value, 2, ',', '.') }} @endif
                            </p>
                            <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="mt-1 inline-block text-xs text-brand-bright hover:underline">Editar</a>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhuma oportunidade nos contatos desta empresa.</li>
                    @endforelse
                </ul>
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="crm-detail__section-title">Projetos da empresa</h2>
                <ul class="mt-4 space-y-3">
                    @forelse($company->projects as $project)
                        <li class="rounded-sm border border-line/70 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <a href="{{ route('admin.projects.edit', $project) }}" class="font-medium text-brand-bright hover:underline">{{ $project->name }}</a>
                                    <p class="text-xs text-mist">
                                        {{ $project->status->label() }}
                                        @if($project->contacts->isNotEmpty())
                                            · {{ $project->contacts->pluck('name')->join(', ') }}
                                        @endif
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('admin.companies.projects.detach', [$company, $project]) }}" data-confirm="Desvincular projeto da empresa?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-mist hover:text-red-300">Remover</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhum projeto vinculado à empresa.</li>
                    @endforelse
                </ul>

                @if($unlinkedProjects->isNotEmpty())
                    <form method="POST" action="{{ route('admin.companies.projects.attach', $company) }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        <select name="project_id" required class="min-w-[12rem] flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
                            <option value="">Vincular projeto…</option>
                            @foreach($unlinkedProjects as $project)
                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-sm border border-line px-3 py-2 text-sm hover:border-brand-bright/50">Vincular</button>
                    </form>
                @endif
            </article>
        </div>
    </div>
@endsection
