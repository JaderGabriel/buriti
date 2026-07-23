@php
    /** @var \App\Data\GoogleCalendarEvent $event */
    $color = $event->googleColor();
    $time = $event->timeLabel();
    $href = $event->htmlLink ?: ($googleCalendarUrl ?? '#');
    $notes = trim((string) ($event->description ?? ''));
@endphp

<article
    class="task-event task-event--agenda task-event--google {{ $color ? 'task-event--gcal task-event--gcal-'.$color->value : '' }}"
    data-task-event
    @if($color) style="--gcal-bg: {{ $color->background() }}; --gcal-fg: {{ $color->foreground() }};" @endif
    x-data="{ open: false }"
>
    <button type="button" class="task-event__hit" @click="open = !open" :aria-expanded="open.toString()" title="{{ $event->title }}">
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
            @if($notes !== '')
                <span class="task-event__notes-preview">📝 {{ \Illuminate\Support\Str::limit($notes, 90) }}</span>
            @endif
        </span>
    </button>

    <div x-cloak x-show="open" class="task-event__editor" @click.stop>
        <div class="task-event__notes-panel">
            <p class="task-event__notes-label">Anotações (Google)</p>
            @if($notes !== '')
                <p class="task-event__notes-body">{{ $notes }}</p>
            @else
                <p class="task-event__notes-empty">Sem anotações neste evento do Google.</p>
            @endif
        </div>
        <div class="task-event__google-actions" style="padding-left: 0; margin-top: 0.5rem;">
            @if($event->meetUrl)
                <a href="{{ $event->meetUrl }}" target="_blank" rel="noopener" class="task-action">Meet</a>
            @endif
            <a href="{{ $href }}" target="_blank" rel="noopener" class="task-action">Abrir no Google</a>
        </div>
    </div>
</article>
