@php
    $summaryParts = array_values(array_filter([
        $task->project?->name,
        $task->contact?->name,
    ]));
    $summary = implode(' · ', $summaryParts);
    $time = $task->due_at?->format('H:i');
    $isDone = $task->status->isDone();
    $activities = $task->activities ?? collect();
    $latest = $activities->first();
    $activityPreview = $latest
        ? trim((string) ($latest->body ?: $latest->subject ?: $latest->type->label()))
        : '';
@endphp

<a
    href="{{ route('admin.tasks.index', array_filter(['view' => $view, 'month' => $month, 'focus' => $task->id])) }}#task-{{ $task->id }}"
    class="task-chip task-chip--{{ $task->status->value }} {{ $isDone ? 'task-chip--success' : '' }}"
    data-task-event
    title="{{ $isDone ? 'Concluída · ' : '' }}{{ $task->title }}{{ $summary ? ' — '.$summary : '' }}{{ $activityPreview !== '' ? ' — '.$activityPreview : '' }}"
>
    @if($isDone)
        <span class="task-chip__check" aria-hidden="true">✓</span>
    @endif
    @if($time)
        <span class="task-chip__time {{ $isDone ? 'task-chip__time--done' : '' }}">{{ $time }}</span>
    @endif
    <span class="task-chip__body">
        <span class="task-chip__title">{{ $task->title }}</span>
        @if($summary !== '')
            <span class="task-chip__summary">{{ $summary }}</span>
        @elseif($activityPreview !== '')
            <span class="task-chip__summary">{{ $latest->type->label() }} · {{ \Illuminate\Support\Str::limit($activityPreview, 32) }}</span>
        @elseif($activities->isNotEmpty())
            <span class="task-chip__summary">{{ $activities->count() }} ativ.</span>
        @endif
    </span>
</a>
