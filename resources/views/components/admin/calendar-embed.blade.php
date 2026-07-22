@props(['src' => null, 'title' => 'Google Agenda'])

@if($src)
    <div {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-line bg-panel']) }}>
        <div class="border-b border-line px-5 py-3 text-sm text-mist">{{ $title }}</div>
        <div class="aspect-[16/10] w-full bg-ink sm:aspect-[16/9]">
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
