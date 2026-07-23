@php
    $summaryParts = array_values(array_filter([
        $task->project?->name,
        $task->contact?->name,
    ]));
    $summary = implode(' · ', $summaryParts);
    $time = $task->due_at?->format('H:i');
@endphp

<a
    href="{{ route('admin.tasks.index', array_filter(['view' => $view, 'month' => $month, 'focus' => $task->id])) }}#task-{{ $task->id }}"
    class="task-chip task-chip--{{ $task->status->value }}"
    data-task-event
    title="{{ $task->title }}{{ $summary ? ' — '.$summary : '' }}"
>
    @if($time)
        <span class="task-chip__time">{{ $time }}</span>
    @endif
    <span class="task-chip__body">
        <span class="task-chip__title">{{ $task->title }}</span>
        @if($summary !== '')
            <span class="task-chip__summary">{{ $summary }}</span>
        @endif
    </span>
</a>
