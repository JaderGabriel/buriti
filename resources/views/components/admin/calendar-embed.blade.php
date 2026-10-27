@props(['src' => null, 'title' => 'Google Agenda', 'fill' => false])

@if($src)
    <div {{ $attributes->merge(['class' => 'calendar-embed overflow-hidden rounded-2xl border border-line bg-panel'.($fill ? ' calendar-embed--fill' : '')]) }}>
        <div class="calendar-embed__head border-b border-line px-5 py-3 text-sm text-mist">{{ $title }}</div>
        <div @class(['calendar-embed__frame w-full bg-ink', 'aspect-[16/10] sm:aspect-[16/9]' => ! $fill])>
            <iframe
                src="{{ $src }}"
                title="{{ $title }}"
                class="h-full w-full border-0"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
            ></iframe>
        </div>
    </div>
@endif
