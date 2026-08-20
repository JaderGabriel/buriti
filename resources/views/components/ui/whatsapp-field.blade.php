@props([
    'name' => 'whatsapp',
    'label' => 'WhatsApp',
    'value' => null,
    'required' => false,
    'hint' => 'Escolha telefone internacional ou nome de usuário do WhatsApp.',
    'defaultCountry' => 'BR',
])

@php
    use App\Support\PhoneNumber;
    use App\Support\WhatsAppLink;

    $current = old($name, $value);
    $kind = WhatsAppLink::kind($current) ?: 'phone';
    $handle = WhatsAppLink::handle($current);
    $countries = PhoneNumber::countries();
    $parsedPhone = $kind === 'phone' && $handle
        ? PhoneNumber::parse('+'.$handle, $defaultCountry)
        : PhoneNumber::parse(null, $defaultCountry);
    $iso = $parsedPhone['iso'] ?: $defaultCountry;
    $national = $kind === 'phone'
        ? PhoneNumber::formatNational((string) $parsedPhone['national'], (string) $iso)
        : '';
    $username = $kind === 'username' ? (string) $handle : '';
@endphp

<div {{ $attributes->class('block text-sm') }} data-whatsapp-field>
    <span class="text-mist">{{ $label }}@if($required) <span class="text-brand-bright">*</span>@endif</span>

    <div
        class="mt-1.5 space-y-2"
        x-data="buritiWhatsAppField({
            mode: @js($kind === 'username' ? 'username' : 'phone'),
            iso: @js($iso),
            national: @js($national),
            username: @js($username),
            countries: @js($countries->values()),
        })"
    >
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                class="rounded-sm border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                :class="mode === 'phone' ? 'border-brand-bright bg-brand/15 text-snow' : 'border-line text-mist hover:text-snow'"
                @click="setMode('phone')"
            >
                Telefone
            </button>
            <button
                type="button"
                class="rounded-sm border px-3 py-1.5 text-xs font-semibold uppercase tracking-wide transition"
                :class="mode === 'username' ? 'border-brand-bright bg-brand/15 text-snow' : 'border-line text-mist hover:text-snow'"
                @click="setMode('username')"
            >
                Usuário
            </button>
        </div>

        <div class="phone-field" x-show="mode === 'phone'" x-cloak>
            <div class="phone-field__grid">
                <label class="phone-field__country">
                    <span class="sr-only">País (DDI)</span>
                    <select class="phone-field__select" x-model="iso" @change="sync()">
                        <template x-for="country in countries" :key="country.iso">
                            <option :value="country.iso" x-text="`${country.flag} ${country.name} (+${country.dial})`"></option>
                        </template>
                    </select>
                </label>
                <label class="phone-field__number">
                    <span class="sr-only">Número WhatsApp</span>
                    <div class="phone-field__control">
                        <span class="phone-field__dial" x-text="dialLabel()"></span>
                        <input
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            placeholder="(11) 98765-4321"
                            class="phone-field__input"
                            x-model="national"
                            @input="formatNational($event); sync()"
                            @blur="formatNational($event); sync()"
                        >
                    </div>
                </label>
            </div>
        </div>

        <label class="block" x-show="mode === 'username'" x-cloak>
            <span class="sr-only">Usuário WhatsApp</span>
            <div class="flex items-center gap-2 rounded-xl border border-line bg-ink px-3 py-2.5 focus-within:ring-1 focus-within:ring-brand-bright">
                <span class="text-mist">@</span>
                <input
                    type="text"
                    autocomplete="username"
                    maxlength="30"
                    placeholder="usuario.exemplo"
                    class="w-full border-0 bg-transparent p-0 text-snow outline-none"
                    x-model="username"
                    @input="sync()"
                >
            </div>
        </label>

        <input type="hidden" name="{{ $name }}" :value="payload" data-whatsapp-payload>
    </div>

    @if(isset($errors) && $errors->has($name))
        <span class="mt-1 block text-xs text-red-400">{{ $errors->first($name) }}</span>
    @endif

    @if($hint)
        <p class="mt-1.5 text-xs text-mist">{{ $hint }}</p>
    @endif
</div>
