@props([
    'email' => null,
    'phone' => null,
    'whatsapp' => null,
    'linkedin' => null,
    'github' => null,
    'telegram' => null,
    'telegramHandle' => null,
])

<div {{ $attributes }}>
    <x-site.contact-icons
        :email="$email"
        :phone="$phone"
        :whatsapp="$whatsapp"
        :linkedin="$linkedin"
        :github="$github"
        :telegram="$telegram"
        :telegram-handle="$telegramHandle"
    >
        {{ $slot }}
    </x-site.contact-icons>
</div>
