@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <a href="{{ route('admin.contacts.index') }}" class="text-sm text-mist hover:text-snow">← Contatos</a>
            <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $contact->name }}</h1>
            <p class="mt-1 text-sm text-mist">
                {{ $contact->status->label() }} · {{ $contact->source->label() }}
                @if($contact->company) · {{ $contact->company }} @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.contacts.edit', $contact) }}" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Editar</a>
            <a href="{{ route('admin.opportunities.create', ['contact_id' => $contact->id]) }}" class="rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-bright">Nova oportunidade</a>
            <form method="POST" action="{{ route('admin.contacts.destroy', $contact) }}" data-confirm="Remover este contato e dados relacionados?">
                @csrf
                @method('DELETE')
                <button class="rounded-sm border border-red-500/40 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">Remover</button>
            </form>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1fr_1.1fr]">
        <div class="space-y-6">
            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Dados</h2>
                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-mist">E-mail</dt>
                        <dd class="mt-1">
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}" class="text-brand-bright hover:underline">{{ $contact->email }}</a>
                            @else — @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-mist">Telefone</dt>
                        <dd class="mt-1">{{ $contact->phone ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-mist">Cargo</dt>
                        <dd class="mt-1">{{ $contact->role ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-mist">Canal preferido</dt>
                        <dd class="mt-1 uppercase tracking-wide">{{ $contact->preferred_channel ?? '—' }}</dd>
                    </div>
                </dl>
                @if($contact->notes)
                    <p class="mt-4 border-t border-line pt-4 text-sm text-mist whitespace-pre-wrap">{{ $contact->notes }}</p>
                @endif
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold">Oportunidades</h2>
                </div>
                <ul class="mt-4 space-y-3">
                    @forelse($contact->opportunities as $opportunity)
                        <li class="rounded-sm border border-line/70 px-3 py-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-medium">{{ $opportunity->title }}</p>
                                <span class="text-xs text-mist">{{ $opportunity->stage->label() }}</span>
                            </div>
                            <p class="text-xs text-mist">
                                {{ $opportunity->project?->name ?? 'Sem projeto' }}
                                @if($opportunity->value) · R$ {{ number_format((float) $opportunity->value, 2, ',', '.') }} @endif
                            </p>
                            <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="mt-1 inline-block text-xs text-brand-bright hover:underline">Editar</a>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhuma oportunidade.</li>
                    @endforelse
                </ul>
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Projetos / produtos</h2>
                <ul class="mt-4 space-y-2">
                    @forelse($contact->projects as $project)
                        <li class="flex items-center justify-between gap-3 text-sm">
                            <span>{{ $project->name }}</span>
                            <form method="POST" action="{{ route('admin.contacts.projects.detach', [$contact, $project]) }}" data-confirm="Desvincular projeto?">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs text-mist hover:text-red-300">Remover</button>
                            </form>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhum projeto vinculado.</li>
                    @endforelse
                </ul>
                <form method="POST" action="{{ route('admin.contacts.projects.attach', $contact) }}" class="mt-4 flex flex-wrap gap-2">
                    @csrf
                    <select name="project_id" required class="min-w-[12rem] flex-1 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
                        <option value="">Vincular projeto…</option>
                        @foreach($allProjects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-sm border border-line px-3 py-2 text-sm hover:border-brand-bright/50">Vincular</button>
                </form>
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Mensagens do site</h2>
                <ul class="mt-4 space-y-2">
                    @forelse($contact->messages as $message)
                        <li>
                            <a href="{{ route('admin.messages.show', $message) }}" class="text-sm text-brand-bright hover:underline">
                                {{ $message->subject }}
                            </a>
                            <span class="text-xs text-mist"> · {{ $message->created_at->format('d/m/Y') }}</span>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhuma mensagem ligada.</li>
                    @endforelse
                </ul>
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Tarefas / agenda</h2>
                <ul class="mt-4 space-y-2">
                    @forelse($contact->tasks as $task)
                        <li class="text-sm">
                            <span class="font-medium">{{ $task->title }}</span>
                            <span class="text-xs text-mist">
                                · {{ $task->status->label() }}
                                @if($task->due_at) · {{ $task->due_at->format('d/m/Y H:i') }} @endif
                            </span>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhuma tarefa ligada. Associe na área de Tarefas.</li>
                    @endforelse
                </ul>
            </article>

            <x-admin.attachments-panel
                :attachable="$contact"
                type="contacts"
                :kinds="['document']"
                layout="folder"
                title="Pasta de arquivos"
                description="Contratos, propostas e PDFs deste contato. Itens ocultos ficam na lixeira."
            />
        </div>

        <div class="space-y-6">
            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Registar atividade</h2>
                <form method="POST" action="{{ route('admin.contacts.activities.store', $contact) }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="block text-sm">
                        <span class="text-mist">Tipo</span>
                        <select name="type" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            @foreach($activityTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input name="subject" label="Assunto" />
                    <label class="block text-sm">
                        <span class="text-mist">Detalhe</span>
                        <textarea name="body" rows="3" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow"></textarea>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Oportunidade (opcional)</span>
                        <select name="opportunity_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            <option value="">—</option>
                            @foreach($contact->opportunities as $opportunity)
                                <option value="{{ $opportunity->id }}">{{ $opportunity->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Tarefa / agenda (opcional)</span>
                        <select name="task_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            <option value="">—</option>
                            @foreach($openTasks as $task)
                                <option value="{{ $task->id }}">{{ $task->title }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input type="datetime-local" name="happened_at" label="Quando" :value="old('happened_at', now()->format('Y-m-d\\TH:i'))" />
                    <x-ui.button type="submit">Guardar atividade</x-ui.button>
                </form>
            </article>

            <article class="rounded-sm border border-line bg-panel p-5">
                <h2 class="font-display text-lg font-semibold">Histórico</h2>
                <ol class="mt-4 space-y-4 border-l border-line pl-4">
                    @forelse($contact->activities as $activity)
                        <li class="relative">
                            <span class="absolute -left-[1.15rem] top-1.5 h-2 w-2 rounded-full bg-brand-bright"></span>
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-bright">{{ $activity->type->label() }}</p>
                                <form method="POST" action="{{ route('admin.contacts.activities.destroy', [$contact, $activity]) }}" data-confirm="Remover atividade?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs text-mist hover:text-red-300">Remover</button>
                                </form>
                            </div>
                            <p class="mt-1 font-medium text-snow">{{ $activity->subject ?: 'Sem assunto' }}</p>
                            @if($activity->body)
                                <p class="mt-1 text-sm text-mist whitespace-pre-wrap">{{ $activity->body }}</p>
                            @endif
                            <p class="mt-1 text-xs text-mist">
                                {{ optional($activity->happened_at)->format('d/m/Y H:i') ?? $activity->created_at->format('d/m/Y H:i') }}
                                @if($activity->user) · {{ $activity->user->name }} @endif
                                @if($activity->opportunity) · {{ $activity->opportunity->title }} @endif
                            </p>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Sem atividades registadas.</li>
                    @endforelse
                </ol>
            </article>
        </div>
    </div>
@endsection
