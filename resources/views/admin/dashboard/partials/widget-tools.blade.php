@php
    $label = $label ?? 'cartão';
@endphp
<button
    type="button"
    class="dash-widget__handle"
    data-dash-handle
    aria-label="Mover cartão {{ $label }}"
    title="Arrastar para mover"
>
    <x-ui.icon name="grip" class="h-4 w-4" />
</button>
<span class="dash-widget__resize" data-dash-resize title="Arrastar para mudar a largura"></span>
