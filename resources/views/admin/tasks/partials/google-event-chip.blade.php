@php
    /** @var \App\Data\GoogleCalendarEvent $event */
    $color = $event->googleColor();
    $time = $event->timeLabel();
    $href = $event->htmlLink ?: ($googleCalendarUrl ?? '#');
    $notes = trim((string) ($event->description ?? ''));
@endphp

<a
    href="{{ $href }}"
    target="_blank"
    rel="noopener"
    class="task-chip task-chip--google {{ $color ? 'task-chip--gcal-'.$color->value : '' }}"
    data-task-event
    @if($color) style="--gcal-bg: {{ $color->background() }}; --gcal-fg: {{ $color->foreground() }};" @endif
    title="Google Agenda · {{ $event->title }}{{ $notes !== '' ? ' — '.$notes : '' }}"
>
    @if($time)
        <span class="task-chip__time">{{ $time }}</span>
    @else
        <span class="task-chip__time">dia</span>
    @endif
    <span class="task-chip__body">
        <span class="task-chip__title">{{ $event->title }}</span>
        <span class="task-chip__summary">
            Google
            @if($notes !== '')
                · 📝
            @endif
        </span>
    </span>
</a>
