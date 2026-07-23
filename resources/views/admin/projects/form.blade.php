@extends('layouts.admin')

@section('content')
    @php
        $editing = $project->exists;
        $currentStatus = old('status', $project->status?->value ?? 'active');
        $progress = $editing ? $project->progressStats() : ['total' => 0, 'done' => 0, 'open' => 0, 'percent' => null, 'source' => 'none'];
        $total = $progress['total'];
        $done = $progress['done'];
        $open = $progress['open'];
    @endphp

    <div class="pm-detail">
        <div class="pm-detail__nav">
            <a href="{{ route('admin.projects.index') }}" class="text-sm text-mist hover:text-snow">← Portfólio</a>
            @if($editing)
                <span class="pm-card__key">PRJ-{{ $project->id }}</span>
            @endif
        </div>

        <div class="pm-detail__header">
            <div>
                <p class="pm-workspace__eyebrow">{{ $editing ? 'Ficha do projeto' : 'Novo projeto' }}</p>
                <h1 class="pm-workspace__title">{{ $editing ? $project->name : 'Abrir projeto' }}</h1>
                <p class="pm-workspace__lead">
                    @if($editing)
                        Atualize status, stack, visibilidade e anexos — no formato de um gestor de projetos.
                    @else
                        Defina nome, escopo e estágio para entrar no board operacional.
                    @endif
                </p>
            </div>
            @if($editing)
                <div class="pm-detail__summary">
                    <span class="pm-status pm-status--{{ $project->status->tone() }}">{{ $project->status->label() }}</span>
                    <div class="pm-detail__metrics">
                        <div>
                            <span class="pm-stat__label">Atividades</span>
                            <strong>{{ $total }}</strong>
                        </div>
                        <div>
                            <span class="pm-stat__label">Em aberto</span>
                            <strong>{{ $open }}</strong>
                        </div>
                        <div>
                            <span class="pm-stat__label">Progresso</span>
                            <strong>{{ $progress['percent'] === null ? '—' : $progress['percent'].'%' }}</strong>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <form method="POST"
              action="{{ $editing ? route('admin.projects.update', $project) : route('admin.projects.store') }}"
              enctype="multipart/form-data"
              class="pm-detail__grid">
            @csrf
            @if($editing) @method('PUT') @endif

            <section class="pm-panel">
                <header class="pm-panel__head">
                    <h2>Resumo</h2>
                    <p>Identidade e narrativa do projeto</p>
                </header>
                <div class="space-y-4">
                    <x-ui.input name="name" label="Nome" :value="old('name', $project->name)" required />
                    <label class="block text-sm">
                        <span class="text-mist">Empresa cliente</span>
                        <select name="company_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                            <option value="">— Sem empresa —</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected((string) old('company_id', $project->company_id) === (string) $company->id)>
                                    {{ $company->displayName() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input type="textarea" name="information" label="Informações" :value="old('information', $project->information)" rows="5" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="category" label="Categoria" :value="old('category', $project->category)" placeholder="BI & Painéis" />
                        <x-ui.input name="stack" label="Stack (separada por vírgulas)" :value="old('stack', is_array($project->stack) ? implode(', ', $project->stack) : $project->stack)" placeholder="Laravel, PHP, Power BI" />
                    </div>
                </div>
            </section>

            <aside class="pm-panel pm-panel--side">
                <header class="pm-panel__head">
                    <h2>Operação</h2>
                    <p>Status e portfólio</p>
                </header>
                <div class="space-y-4">
                    <label class="block text-sm">
                        <span class="text-mist">Status</span>
                        <select name="status" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input type="number" name="sort_order" label="Ordem no portfólio" :value="old('sort_order', $project->sort_order ?? 0)" min="0" />

                    <div class="space-y-3 rounded-sm border border-line bg-ink/40 p-3">
                        <label class="flex items-start gap-2 text-sm text-mist">
                            <input type="checkbox" name="is_public" value="1" class="mt-1" @checked(old('is_public', $project->is_public))>
                            <span>
                                <strong class="text-snow">Exibir no site</strong>
                                <span class="block text-xs">Aparece na seção Portfólio da landing.</span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm text-mist">
                            <input type="checkbox" name="repo_is_private" value="1" class="mt-1" @checked(old('repo_is_private', $project->repo_is_private))>
                            <span>
                                <strong class="text-snow">Repositório privado</strong>
                                <span class="block text-xs">Sem links públicos de Site/GitHub na home.</span>
                            </span>
                        </label>
                    </div>
                </div>
            </aside>

            <section class="pm-panel">
                <header class="pm-panel__head">
                    <h2>Links e artefatos</h2>
                    <p>Canais e documentos do projeto</p>
                </header>
                <div class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input type="url" name="website_url" label="Link do site (público)" :value="old('website_url', $project->website_url)" />
                        <x-ui.input type="url" name="github_url" label="GitHub (interno se repo privado)" :value="old('github_url', $project->github_url)" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="text-mist">Logo</span>
                            <input type="file" name="logo" accept="image/*" class="mt-1.5 w-full text-mist file:mr-3 file:rounded-sm file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:text-white">
                            @if($project->logoUrl())
                                <img src="{{ $project->logoUrl() }}" alt="" class="mt-3 h-16 w-16 rounded-sm object-cover">
                            @endif
                        </label>
                        <label class="block text-sm">
                            <span class="text-mist">Contrato (PDF/DOC/imagem)</span>
                            <input type="file" name="contract" accept=".pdf,.doc,.docx,image/*" class="mt-1.5 w-full text-mist file:mr-3 file:rounded-sm file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:text-white">
                            @if($project->contractUrl())
                                <a href="{{ $project->contractUrl() }}" target="_blank" class="mt-3 inline-block text-sm text-brand-bright">Abrir contrato atual</a>
                            @endif
                        </label>
                    </div>
                </div>
            </section>

            <div class="pm-detail__submit">
                <a href="{{ route('admin.projects.index') }}" class="pm-btn pm-btn--ghost">Cancelar</a>
                <button type="submit" class="pm-btn pm-btn--primary">
                    {{ $editing ? 'Salvar alterações' : 'Criar projeto' }}
                </button>
            </div>
        </form>

        @if($editing)
            <section class="pm-panel mt-6">
                <header class="pm-panel__head">
                    <div>
                        <h2>Etapas / to-do</h2>
                        <p>Marque a conclusão e registre observações — o % do projeto segue estas etapas.</p>
                    </div>
                    <div class="pm-detail__metrics">
                        <div>
                            <span class="pm-stat__label">Concluídas</span>
                            <strong>{{ $done }}/{{ $total }}</strong>
                        </div>
                        <div>
                            <span class="pm-stat__label">Progresso</span>
                            <strong>{{ $progress['percent'] === null ? '—' : $progress['percent'].'%' }}</strong>
                        </div>
                    </div>
                </header>

                <div class="pm-steps">
                    @forelse($project->steps as $step)
                        <article @class(['pm-step', 'is-done' => $step->is_completed])>
                            <form method="POST" action="{{ route('admin.projects.steps.update', [$project, $step]) }}" class="pm-step__form">
                                @csrf
                                @method('PUT')
                                <div class="pm-step__row">
                                    <label class="pm-step__check">
                                        <input type="hidden" name="is_completed" value="0">
                                        <input type="checkbox" name="is_completed" value="1" @checked(old('is_completed_'.$step->id, $step->is_completed))>
                                        <span class="sr-only">Concluída</span>
                                    </label>
                                    <div class="pm-step__fields">
                                        <input
                                            type="text"
                                            name="title"
                                            value="{{ old('title_'.$step->id, $step->title) }}"
                                            required
                                            class="pm-step__title"
                                            placeholder="Título da etapa"
                                        >
                                        <textarea
                                            name="notes"
                                            rows="2"
                                            class="pm-step__notes"
                                            placeholder="Observações desta etapa…"
                                        >{{ old('notes_'.$step->id, $step->notes) }}</textarea>
                                        @if($step->is_completed && $step->completed_at)
                                            <p class="pm-step__meta">Concluída em {{ $step->completed_at->format('d/m/Y H:i') }}</p>
                                        @endif
                                    </div>
                                </div>
                                <div class="pm-step__ops">
                                    <button type="submit" class="pm-card__btn">Salvar</button>
                                </div>
                            </form>
                            <form method="POST" action="{{ route('admin.projects.steps.destroy', [$project, $step]) }}" data-confirm="Remover esta etapa?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="pm-card__btn pm-card__btn--danger">Excluir</button>
                            </form>
                        </article>
                    @empty
                        <p class="pm-board__empty">Ainda sem etapas. Adicione o primeiro to-do abaixo.</p>
                    @endforelse
                </div>

                <form method="POST" action="{{ route('admin.projects.steps.store', $project) }}" class="pm-step-create mt-4">
                    @csrf
                    <p class="text-sm font-medium text-snow">Nova etapa</p>
                    <div class="mt-3 grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div class="space-y-3">
                            <input type="text" name="title" required placeholder="Ex.: Levantamento, Homologação, Go-live…" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-sm text-snow">
                            <textarea name="notes" rows="2" placeholder="Observações iniciais (opcional)" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-sm text-snow"></textarea>
                        </div>
                        <button type="submit" class="pm-btn pm-btn--primary self-start">Adicionar</button>
                    </div>
                </form>
            </section>

            <div class="mt-6">
                <x-admin.attachments-panel
                    :attachable="$project"
                    type="projects"
                    :kinds="['document', 'media', 'photo']"
                    layout="folder"
                    title="Pasta do projeto"
                    description="Documentos, mídias e fotos internas. Não alteram o portfólio público da home."
                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*,video/*,audio/*"
                />
            </div>
        @endif
    </div>
@endsection
