@props([
    'email' => null,
    'phone' => null,
    'whatsapp' => null,
])

<ul {{ $attributes->merge(['class' => 'space-y-4 text-sm']) }}>
    @if($email)
        <li>
            <span class="text-mist">E-mail</span><br>
            <a href="mailto:{{ $email }}" class="break-all text-brand-bright hover:underline">{{ $email }}</a>
        </li>
    @endif
    @if($phone)
        <li>
            <span class="text-mist">Telefone</span><br>
            <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="text-snow">{{ $phone }}</a>
        </li>
    @endif
    @if($whatsapp)
        <li>
            <span class="text-mist">WhatsApp</span><br>
            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}" target="_blank" rel="noopener" class="text-brand-bright hover:underline">{{ $whatsapp }}</a>
        </li>
    @endif
    {{ $slot }}
</ul>
