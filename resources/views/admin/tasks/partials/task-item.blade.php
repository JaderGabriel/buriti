@php
    $task = $task ?? null;
    $view = $view ?? 'calendar';
    $month = $month ?? null;
    $compact = (bool) ($compact ?? false);
    $isDone = $task?->status?->isDone() ?? false;
    $activities = $task?->activities ?? collect();
    $latestActivity = $activities
        ->sortByDesc(fn ($activity) => optional($activity->happened_at ?? $activity->created_at)->timestamp ?? 0)
        ->first();
    $activityPreview = $latestActivity
        ? trim((string) ($latestActivity->body ?: $latestActivity->subject ?: $latestActivity->type->label()))
        : '';

    $summaryParts = array_values(array_filter([
        $task?->project?->name,
        $task?->contact?->name,
        $isDone ? null : $task?->status?->label(),
    ]));
    $summary = implode(' · ', $summaryParts);
    $time = $task?->due_at?->format('H:i');
@endphp

@if($task)
<article
    id="task-{{ $task->id }}"
    class="task-event {{ $compact ? 'task-event--compact' : 'task-event--agenda' }} task-event--{{ $task->status->value }} {{ $isDone ? 'task-event--success' : '' }} {{ $task->googleColor() ? 'task-event--gcal task-event--gcal-'.$task->googleColor()->value : '' }}"
    data-task-event
    @if($task->googleColor())
        style="--gcal-bg: {{ $task->googleColor()->background() }}; --gcal-fg: {{ $task->googleColor()->foreground() }};"
    @endif
>
    <button
        type="button"
        class="task-event__hit"
        data-task-open="{{ $task->id }}"
        title="Editar ou concluir: {{ $task->title }}"
    >
        @if($isDone)
            <span class="task-event__check" aria-hidden="true" title="Concluída">✓</span>
        @endif
        @if($time)
            <span class="task-event__time {{ $isDone ? 'task-event__time--done' : '' }}">{{ $time }}</span>
        @endif
        <span class="task-event__main">
            <span class="task-event__title">
                @if($isDone)
                    <span class="task-event__done-badge">Concluída</span>
                @endif
                {{ $task->title }}
            </span>
            @if($summary !== '')
                <span class="task-event__summary">{{ $summary }}</span>
            @endif
            @if($activityPreview !== '')
                <span class="task-event__notes-preview">
                    {{ $latestActivity->type->label() }} · {{ \Illuminate\Support\Str::limit($activityPreview, $compact ? 40 : 80) }}
                </span>
            @elseif($activities->isNotEmpty())
                <span class="task-event__notes-preview">{{ $activities->count() }} atividade{{ $activities->count() === 1 ? '' : 's' }}</span>
            @endif
        </span>
    </button>
</article>
@endif
