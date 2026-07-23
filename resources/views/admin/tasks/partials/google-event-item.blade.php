@php
    /** @var \App\Data\GoogleCalendarEvent $event */
    $color = $event->googleColor();
    $time = $event->timeLabel();
    $href = $event->htmlLink ?: ($googleCalendarUrl ?? '#');
@endphp

<article
    class="task-event task-event--agenda task-event--google {{ $color ? 'task-event--gcal task-event--gcal-'.$color->value : '' }}"
    data-task-event
    @if($color) style="--gcal-bg: {{ $color->background() }}; --gcal-fg: {{ $color->foreground() }};" @endif
>
    <a href="{{ $href }}" target="_blank" rel="noopener" class="task-event__hit">
        @if($time)
            <span class="task-event__time">{{ $time }}</span>
        @else
            <span class="task-event__time">dia</span>
        @endif
        <span class="task-event__main">
            <span class="task-event__title">{{ $event->title }}</span>
            <span class="task-event__summary">
                Google Agenda
                @if($event->meetUrl)
                    · Meet
                @endif
            </span>
        </span>
    </a>
    <div class="task-event__google-actions">
        @if($event->meetUrl)
            <a href="{{ $event->meetUrl }}" target="_blank" rel="noopener" class="task-action">Meet</a>
        @endif
        <a href="{{ $href }}" target="_blank" rel="noopener" class="task-action">Abrir no Google</a>
    </div>
</article>
