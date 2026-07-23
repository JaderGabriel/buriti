@php
    $view = $view ?? 'calendar';
    $month = $month ?? null;
    $projects = $projects ?? collect();
    $contacts = $contacts ?? collect();
    $statusLabels = $statusLabels ?? [];
    $priorityLabels = $priorityLabels ?? [];
    $defaultDueAt = $defaultDueAt ?? now()->setTime(9, 0)->format('Y-m-d\TH:i');
@endphp

<div
    id="task-create-dialog"
    class="task-create-dialog"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="task-create-title"
    aria-hidden="true"
>
    <button type="button" class="task-create-dialog__backdrop" data-task-create-close aria-label="Fechar"></button>
    <div class="task-create-dialog__panel">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-brand-bright">Nova atividade</p>
                <h2 id="task-create-title" class="mt-1 font-display text-lg font-semibold text-snow" data-task-create-heading>Novo compromisso</h2>
            </div>
            <button type="button" class="rounded-sm border border-line px-2.5 py-1 text-sm text-mist hover:text-snow" data-task-create-close aria-label="Fechar">✕</button>
        </div>

        <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="return_view" value="{{ $view }}">
            <input type="hidden" name="return_month" value="{{ $month }}">

            <label class="block text-sm">
                <span class="text-mist">Título</span>
                <input
                    data-task-create-title
                    name="title"
                    required
                    maxlength="180"
                    placeholder="Ex.: Reunião de alinhamento"
                    class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow outline-none focus:border-brand-bright/50"
                >
            </label>

            <label class="block text-sm">
                <span class="text-mist">Data e hora</span>
                <input
                    data-task-create-due
                    type="datetime-local"
                    name="due_at"
                    required
                    value="{{ $defaultDueAt }}"
                    class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow outline-none focus:border-brand-bright/50"
                >
            </label>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-mist">Prioridade</span>
                    <select name="priority" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow">
                        @foreach($priorityLabels as $value => $label)
                            <option value="{{ $value }}" @selected($value === 'medium')>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Status</span>
                    <select name="status" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow">
                        @foreach($statusLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-mist">Projeto</span>
                    <select name="project_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="">Sem projeto</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm">
                    <span class="text-mist">Contato</span>
                    <select name="contact_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow">
                        <option value="">Sem contato</option>
                        @foreach($contacts as $contact)
                            <option value="{{ $contact->id }}">{{ $contact->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <textarea name="description" rows="2" placeholder="Notas (opcional)" class="w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow"></textarea>

            <label class="flex items-center gap-2 text-sm text-mist">
                <input type="hidden" name="want_meet" value="0">
                <input type="checkbox" name="want_meet" value="1" checked class="rounded border-line">
                Preparar com Google Meet
            </label>

            <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
                <button type="button" class="rounded-sm border border-line px-4 py-2 text-sm text-mist hover:text-snow" data-task-create-close>Cancelar</button>
                <button type="submit" class="rounded-sm bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-bright">Criar</button>
            </div>
        </form>
    </div>
</div>
