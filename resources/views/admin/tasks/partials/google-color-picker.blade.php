@php
    $palette = $googleEventColors ?? \App\Enums\GoogleEventColor::palette();
    $selected = old('google_color_id', isset($task) ? $task->googleColor()?->value : null);
    $name = $name ?? 'google_color_id';
    $compact = (bool) ($compact ?? false);
@endphp

<div class="gcal-color-picker {{ $compact ? 'gcal-color-picker--compact' : '' }}">
    <p class="gcal-color-picker__label">{{ $label ?? 'Cor no Google Agenda' }}</p>
    <div class="gcal-color-picker__swatches" role="radiogroup" aria-label="Cor do evento Google">
        <label class="gcal-color-picker__swatch gcal-color-picker__swatch--none {{ blank($selected) ? 'is-active' : '' }}" title="Cor padrão da agenda">
            <input type="radio" name="{{ $name }}" value="" @checked(blank($selected)) class="sr-only">
            <span></span>
        </label>
        @foreach($palette as $color)
            <label
                class="gcal-color-picker__swatch {{ (string) $selected === (string) $color['id'] ? 'is-active' : '' }}"
                title="{{ $color['label'] }}"
                style="--gcal-swatch: {{ $color['background'] }}"
            >
                <input
                    type="radio"
                    name="{{ $name }}"
                    value="{{ $color['id'] }}"
                    @checked((string) $selected === (string) $color['id'])
                    class="sr-only"
                >
                <span></span>
            </label>
        @endforeach
    </div>
</div>
