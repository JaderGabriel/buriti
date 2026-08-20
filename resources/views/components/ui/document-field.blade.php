@props([
    'name' => 'document',
    'label' => 'CPF / CNPJ',
    'value' => null,
    'required' => false,
    'hint' => 'Digite o CPF ou CNPJ — a máscara é aplicada automaticamente.',
    'placeholder' => '000.000.000-00 ou 00.000.000/0001-00',
])

@php
    use App\Support\BrazilianDocument;

    $current = old($name, $value);
    $display = BrazilianDocument::format($current) ?? $current;
@endphp

<label {{ $attributes->only('class')->merge(['class' => 'block text-sm']) }}>
    <span class="text-mist">{{ $label }}@if($required) <span class="text-brand-bright">*</span>@endif</span>
    <input
        type="text"
        name="{{ $name }}"
        value="{{ $display }}"
        @required($required)
        inputmode="numeric"
        autocomplete="off"
        maxlength="18"
        placeholder="{{ $placeholder }}"
        data-document-mask
        {{ $attributes->except('class')->merge(['class' => 'mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1']) }}
    >
    @if (isset($errors) && $errors->has($name))
        <span class="mt-1 block text-xs text-red-400">{{ $errors->first($name) }}</span>
    @endif
    @if($hint)
        <p class="mt-1.5 text-xs text-mist">{{ $hint }}</p>
    @endif
</label>
