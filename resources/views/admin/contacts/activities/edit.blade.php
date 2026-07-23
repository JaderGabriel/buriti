@extends('layouts.admin')

@section('content')
    @php
        $happenedValue = old(
            'happened_at',
            optional($activity->happened_at)->timezone(config('app.timezone'))->format('Y-m-d\\TH:i')
        );
        $relatedActivities = $relatedActivities ?? collect();
    @endphp

    <div class="mb-8">
        <a href="{{ route('admin.contacts.show', $contact) }}" class="text-sm text-mist hover:text-snow">← {{ $contact->name }}</a>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">Editar atividade</h1>
        <p class="mt-1 text-sm text-mist">
            {{ $activity->type->label() }}
            · #{{ $activity->id }}
            @if($activity->user)
                · registada por {{ $activity->user->name }}
            @endif
        </p>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
        <form
            method="POST"
            action="{{ route('admin.contacts.activities.update', [$contact, $activity]) }}"
            class="max-w-2xl space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6"
        >
            @csrf
            @method('PUT')

            <label class="block text-sm">
                <span class="text-mist">Tipo</span>
                <select name="type" required class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    @foreach($activityTypes as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $activity->type->value) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <x-ui.input name="subject" label="Assunto" :value="old('subject', $activity->subject)" />

            <label class="block text-sm">
                <span class="text-mist">Detalhe</span>
                <textarea name="body" rows="5" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">{{ old('body', $activity->body) }}</textarea>
            </label>

            <label class="block text-sm">
                <span class="text-mist">Oportunidade (opcional)</span>
                <select name="opportunity_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    <option value="">—</option>
                    @foreach($contact->opportunities as $opportunity)
                        <option
                            value="{{ $opportunity->id }}"
                            @selected((string) old('opportunity_id', $activity->opportunity_id) === (string) $opportunity->id)
                        >{{ $opportunity->title }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm">
                <span class="text-mist">Tarefa / agenda (opcional)</span>
                <select name="task_id" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    <option value="">—</option>
                    @foreach($linkableTasks as $task)
                        <option
                            value="{{ $task->id }}"
                            @selected((string) old('task_id', $activity->task_id) === (string) $task->id)
                        >
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
                    @checked(old('complete_task', false))
                >
                <span>
                    <span class="text-snow">Marcar reunião/tarefa como concluída</span>
                    <span class="mt-0.5 block text-xs text-mist">Só se houver tarefa vinculada. Pode acrescentar notas sem fechar a reunião.</span>
                </span>
            </label>

            <x-ui.input type="datetime-local" name="happened_at" label="Quando" :value="$happenedValue" />

            <div class="flex flex-wrap items-center gap-3 pt-2">
                <x-ui.button type="submit">Guardar alterações</x-ui.button>
                <a href="{{ route('admin.contacts.show', $contact) }}" class="text-sm text-mist hover:text-snow">Cancelar</a>
            </div>
        </form>

        <aside class="space-y-4">
            <div class="rounded-sm border border-line bg-panel p-4 text-sm">
                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Contato</p>
                <p class="mt-2 font-semibold text-snow">{{ $contact->name }}</p>
                @if($contact->companyLabel())
                    <p class="mt-1 text-mist">{{ $contact->companyLabel() }}</p>
                @endif
                <a href="{{ route('admin.contacts.show', $contact) }}" class="mt-3 inline-block text-brand-bright hover:underline">Abrir ficha</a>
            </div>

            @if($activity->task)
                <div class="rounded-sm border border-line bg-panel p-4 text-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Reunião vinculada</p>
                    <p class="mt-2 font-semibold text-snow">{{ $activity->task->title }}</p>
                    <p class="mt-1 text-mist">{{ $activity->task->status->label() }}</p>
                    <a
                        href="{{ route('admin.tasks.index', ['view' => 'agenda']) }}#task-{{ $activity->task->id }}"
                        class="mt-3 inline-block text-brand-bright hover:underline"
                    >Abrir na agenda</a>
                </div>
            @endif

            @if($relatedActivities->isNotEmpty())
                <div class="activity-trend rounded-sm border border-line bg-panel p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Histórico nesta reunião</p>
                    <p class="mt-1 text-xs text-mist">Notas e actividades no mesmo compromisso, em ordem.</p>
                    <ol class="activity-trend__list">
                        @foreach($relatedActivities as $related)
                            <li class="activity-trend__item activity-trend__item--{{ $related->type->tone() }} {{ $related->id === $activity->id ? 'is-current' : '' }}">
                                <span class="activity-trend__rail" aria-hidden="true"></span>
                                <span class="activity-trend__dot" aria-hidden="true"></span>
                                <div class="min-w-0">
                                    <div class="activity-trend__top">
                                        <span class="activity-trend__type">{{ $related->type->label() }}</span>
                                        <time>{{ optional($related->happened_at ?? $related->created_at)->format('d/m H:i') }}</time>
                                    </div>
                                    @if($related->id === $activity->id)
                                        <p class="activity-trend__subject">{{ $related->subject ?: 'Sem assunto' }} <span class="activity-trend__now">(esta)</span></p>
                                    @else
                                        <a
                                            href="{{ route('admin.contacts.activities.edit', [$related->contact_id ?: $contact, $related]) }}"
                                            class="activity-trend__subject activity-trend__subject--link"
                                        >{{ $related->subject ?: 'Sem assunto' }}</a>
                                    @endif
                                    @if($related->user)
                                        <p class="activity-trend__meta">{{ $related->user->name }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('admin.contacts.activities.destroy', [$contact, $activity]) }}"
                data-confirm="Remover esta atividade?"
                class="rounded-sm border border-line border-red-500/20 bg-panel p-4"
            >
                @csrf
                @method('DELETE')
                <p class="text-xs text-mist">Esta ação não pode ser desfeita.</p>
                <button type="submit" class="mt-3 text-sm font-semibold text-red-300 hover:text-red-200">Remover atividade</button>
            </form>
        </aside>
    </div>
@endsection
