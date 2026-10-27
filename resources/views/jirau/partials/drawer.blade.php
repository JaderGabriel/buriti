<div class="jirau-drawer-wrap">
    <a href="{{ route('jirau.show', $jirau) }}" class="jirau-drawer__backdrop" aria-label="Fechar cartão"></a>
    <aside class="jirau-drawer" role="dialog" aria-labelledby="jirau-card-title">
        <header class="jirau-drawer__head">
            <p class="jirau-card__key">{{ $card->key() }} · {{ $card->type->label() }} · {{ $card->column?->name }}</p>
            <h2 id="jirau-card-title">{{ $card->title }}</h2>
            <a href="{{ route('jirau.show', $jirau) }}" class="jirau-drawer__close">Fechar</a>
        </header>

        <form method="POST" action="{{ route('jirau.cards.update', [$jirau, $card]) }}" class="jirau-drawer__form">
            @csrf
            @method('PUT')
            <x-ui.input name="title" label="Título" :value="old('title', $card->title)" required />
            <x-ui.input type="textarea" name="description" label="Descrição" :value="old('description', $card->description)" rows="6" />

            <label class="block text-sm">
                <span class="text-mist">Prioridade</span>
                <select name="priority" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                    @foreach($priorities as $value => $label)
                        <option value="{{ $value }}" @selected(old('priority', $card->priority->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @if($isAdmin)
                <div class="jirau-compose__grid">
                    <label class="block text-sm">
                        <span class="text-mist">Tipo</span>
                        <select name="type" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                            @foreach($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $card->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Estimativa</span>
                        <select name="estimate" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                            <option value="">—</option>
                            @foreach($estimates as $pts)
                                <option value="{{ $pts }}" @selected((int) old('estimate', $card->estimate) === $pts)>{{ $pts }} pts</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Responsável</span>
                        <select name="assigned_to" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5">
                            <option value="">Não alocado</option>
                            @foreach($assignees ?? [] as $person)
                                <option value="{{ $person->id }}" @selected((string) old('assigned_to', $card->assigned_to) === (string) $person->id)>
                                    {{ $person->name }}{{ $person->jirau_role ? ' · '.$person->jirau_role->label() : ' · Admin' }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <x-ui.input type="datetime-local" name="due_at" label="Prazo" :value="old('due_at', $card->due_at?->format('Y-m-d\TH:i'))" />
                </div>
                <fieldset class="jirau-labels-pick">
                    <legend>Etiquetas</legend>
                    @foreach($jirau->labels as $label)
                        <label>
                            <input type="checkbox" name="label_ids[]" value="{{ $label->id }}" @checked($card->labels->contains($label->id))>
                            <span class="jirau-label" style="--jirau-label: {{ $label->color }}">{{ $label->name }}</span>
                        </label>
                    @endforeach
                </fieldset>
            @else
                <p class="jirau-hint">
                    Faixa e estimativa ficam com o admin do quadro.
                    @if($card->estimate)
                        Estimativa atual: <strong>{{ $card->estimate }} pts</strong>.
                    @else
                        Ainda sem estimativa.
                    @endif
                </p>
            @endif

            <button type="submit" class="pm-btn pm-btn--primary">Salvar</button>
        </form>

        @if($isAdmin)
            <section class="jirau-drawer__block">
                <h3>Etapa do projeto</h3>
                @if($card->projectStep)
                    <p class="jirau-hint">
                        Junta à etapa <strong>{{ $card->projectStep->title }}</strong>
                        ({{ $card->projectStep->is_completed ? 'concluída' : 'aberta' }}).
                        Mover para a faixa final marca a etapa.
                    </p>
                    <form method="POST" action="{{ route('jirau.cards.step.detach', [$jirau, $card]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="pm-btn pm-btn--ghost">Desvincular</button>
                    </form>
                @else
                    <p class="jirau-hint">Junte este cartão a uma etapa existente ou envie como nova etapa da ficha.</p>
                    @if($unlinkedSteps->isNotEmpty())
                        <form method="POST" action="{{ route('jirau.cards.step.attach', [$jirau, $card]) }}" class="jirau-inline">
                            @csrf
                            <select name="step_id" class="jirau-input" required>
                                @foreach($unlinkedSteps as $step)
                                    <option value="{{ $step->id }}">{{ $step->title }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="pm-btn pm-btn--ghost">Juntar</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('jirau.cards.step.attach', [$jirau, $card]) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="pm-btn pm-btn--primary">Criar etapa a partir deste cartão</button>
                    </form>
                @endif
            </section>
        @endif

        <section class="jirau-drawer__block">
            <h3>Checklist</h3>
            <ul class="jirau-check">
                @foreach($card->checklistItems as $item)
                    <li @class(['is-done' => $item->is_done])>
                        <form method="POST" action="{{ route('jirau.cards.checklist.toggle', [$jirau, $card, $item]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit">{{ $item->is_done ? '☑' : '☐' }} {{ $item->title }}</button>
                        </form>
                        @if($isAdmin)
                            <form method="POST" action="{{ route('jirau.cards.checklist.destroy', [$jirau, $card, $item]) }}" data-confirm="Remover item?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="jirau-check__del">✕</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('jirau.cards.checklist.store', [$jirau, $card]) }}" class="jirau-inline">
                @csrf
                <input type="text" name="title" required placeholder="Novo item" class="jirau-input">
                <button type="submit" class="pm-btn pm-btn--ghost">Adicionar</button>
            </form>
        </section>

        <section class="jirau-drawer__block">
            <h3>Atividade</h3>
            <ul class="jirau-comments">
                @forelse($card->comments as $comment)
                    <li>
                        <strong>{{ $comment->author?->name ?? 'Alguém' }}</strong>
                        <time>{{ $comment->created_at?->format('d/m H:i') }}</time>
                        <p>{{ $comment->body }}</p>
                    </li>
                @empty
                    <li class="jirau-members__empty">Sem comentários ainda.</li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('jirau.cards.comments.store', [$jirau, $card]) }}">
                @csrf
                <x-ui.input type="textarea" name="body" label="Comentar" rows="3" required />
                <button type="submit" class="pm-btn pm-btn--ghost">Publicar</button>
            </form>
        </section>

        @if($isAdmin)
            <form method="POST" action="{{ route('jirau.cards.archive', [$jirau, $card]) }}" class="mt-4" data-confirm="Arquivar este cartão?">
                @csrf
                <button type="submit" class="pm-card__btn pm-card__btn--danger">Arquivar</button>
            </form>
        @endif
    </aside>
</div>
