@php
    $pickerContacts = $pickerContacts ?? collect();
    $activityTypes = $activityTypes ?? [];
    $openTasks = $openTasks ?? collect();
    $preselectedIds = collect($preselectedContactIds ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
@endphp

<div
    id="bulk-activity-dialog"
    class="bulk-activity-dialog"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="bulk-activity-title"
    data-preselected='@json($preselectedIds)'
>
    <button type="button" class="bulk-activity-dialog__backdrop" data-bulk-activity-close aria-label="Fechar"></button>
    <div class="bulk-activity-dialog__panel">
        <header class="bulk-activity-dialog__header">
            <div>
                <p class="bulk-activity-dialog__eyebrow">Condução comercial</p>
                <h2 id="bulk-activity-title" class="bulk-activity-dialog__title">Registar atividade</h2>
                <p class="bulk-activity-dialog__lead">Marque um ou vários contatos — a mesma atividade é replicada em cada ficha.</p>
            </div>
            <button type="button" class="bulk-activity-dialog__close" data-bulk-activity-close aria-label="Fechar">✕</button>
        </header>

        <form method="POST" action="{{ route('admin.contacts.activities.bulk') }}" class="bulk-activity-dialog__form" data-bulk-activity-form>
            @csrf
            <div data-bulk-activity-ids></div>

            <div class="bulk-activity-dialog__grid">
                <section class="bulk-activity-dialog__contacts">
                    <div class="bulk-activity-dialog__contacts-head">
                        <label class="bulk-activity-dialog__check-all">
                            <input type="checkbox" data-bulk-activity-all>
                            <span>Contatos</span>
                        </label>
                        <span class="bulk-activity-dialog__count" data-bulk-activity-count>0 selecionado(s)</span>
                    </div>
                    <input type="search" class="bulk-activity-dialog__search" placeholder="Filtrar contatos…" data-bulk-activity-filter autocomplete="off">
                    <ul class="bulk-activity-dialog__list">
                        @forelse($pickerContacts as $picker)
                            <li
                                class="bulk-activity-dialog__item"
                                data-bulk-activity-item
                                data-name="{{ Str::lower($picker->name.' '.($picker->companyLabel() ?? '')) }}"
                            >
                                <label>
                                    <input
                                        type="checkbox"
                                        value="{{ $picker->id }}"
                                        data-bulk-activity-pick
                                    >
                                    <span>
                                        <strong>{{ $picker->name }}</strong>
                                        <small>{{ $picker->companyLabel() ?? 'Sem empresa' }}</small>
                                    </span>
                                </label>
                            </li>
                        @empty
                            <li class="bulk-activity-dialog__empty">Nenhum contato disponível.</li>
                        @endforelse
                    </ul>
                    <p class="bulk-activity-dialog__flash" data-bulk-activity-flash hidden></p>
                </section>

                <section class="bulk-activity-dialog__fields">
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
                        <textarea name="body" rows="4" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow" placeholder="O que aconteceu, próximos passos…"></textarea>
                    </label>
                    <label class="block text-sm">
                        <span class="text-mist">Tarefa / agenda (opcional)</span>
                        <select name="task_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                            <option value="">—</option>
                            @foreach($openTasks as $task)
                                <option value="{{ $task->id }}">
                                    {{ $task->title }}
                                    @if($task->status->isDone()) (concluída) @endif
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="flex items-start gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="complete_task"
                            value="1"
                            class="mt-1 rounded-sm border-line bg-ink/40 text-brand-bright focus:ring-brand-bright"
                        >
                        <span>
                            <span class="text-snow">Marcar reunião/tarefa como concluída</span>
                            <span class="mt-0.5 block text-xs text-mist">Opcional — só afecta a tarefa vinculada.</span>
                        </span>
                    </label>
                    <x-ui.input type="datetime-local" name="happened_at" label="Quando" :value="old('happened_at', now()->format('Y-m-d\\TH:i'))" />

                    <div class="bulk-activity-dialog__actions">
                        <button type="button" class="rounded-sm border border-line px-4 py-2 text-sm text-mist hover:text-snow" data-bulk-activity-close>Cancelar</button>
                        <button type="submit" class="rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-bright">
                            Guardar para selecionados
                        </button>
                    </div>
                </section>
            </div>
        </form>
    </div>
</div>
