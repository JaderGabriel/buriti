@props([
    'label' => 'Telefone',
    'value' => null,
    'required' => false,
    'hint' => 'Escolha o país. Digite só DDD e número, sem o DDI.',
    'countryName' => 'phone_country',
    'numberName' => 'phone_number',
    'defaultCountry' => 'BR',
])

@php
    use App\Support\PhoneNumber;

    $parsed = PhoneNumber::parse(old('phone', $value), old($countryName, $defaultCountry));
    $iso = old($countryName, $parsed['iso'] ?: $defaultCountry);
    $nationalRaw = old($numberName, $parsed['national']);
    $national = PhoneNumber::formatNational((string) $nationalRaw, (string) $iso);
    $countries = PhoneNumber::countries();
@endphp

<div {{ $attributes->class('block text-sm') }}>
    <span class="text-mist">{{ $label }}@if($required) <span class="text-brand-bright">*</span>@endif</span>

    <div
        class="phone-field mt-1.5"
        x-data="buritiPhoneCountryField(@js($countries->values()), @js($iso))"
        data-phone-field
    >
        <div class="phone-field__grid">
            <label class="phone-field__country">
                <span class="sr-only">País (DDI)</span>
                <select
                    name="{{ $countryName }}"
                    x-model="iso"
                    @required($required)
                    class="phone-field__select"
                >
                    @foreach($countries as $country)
                        <option value="{{ $country['iso'] }}" @selected($iso === $country['iso'])>
                            {{ $country['flag'] }} {{ $country['name'] }} (+{{ $country['dial'] }})
                        </option>
                    @endforeach
                </select>
            </label>

            <label class="phone-field__number">
                <span class="sr-only">Número</span>
                <div class="phone-field__control">
                    <span class="phone-field__dial" x-text="dialLabel()"></span>
                    <input
                        type="tel"
                        name="{{ $numberName }}"
                        value="{{ $national }}"
                        @required($required)
                        inputmode="numeric"
                        autocomplete="tel-national"
                        placeholder="38 99175-8416"
                        class="phone-field__input"
                        data-phone-national
                        x-on:input="formatNational($event)"
                    >
                </div>
            </label>
        </div>
    </div>

    @if(isset($errors) && ($errors->has('phone') || $errors->has($numberName) || $errors->has($countryName)))
        <span class="mt-1 block text-xs text-red-400">
            {{ $errors->first($numberName) ?: ($errors->first($countryName) ?: $errors->first('phone')) }}
        </span>
    @endif

    @if($hint)
        <p class="mt-1.5 text-xs text-mist">{{ $hint }}</p>
    @endif
</div>
