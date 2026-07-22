@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Configurações</h1>
        <p class="mt-1 text-mist">Contatos públicos e integração com Google Agenda</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="max-w-3xl space-y-4 rounded-2xl border border-line bg-panel p-5 sm:p-6">
        @csrf
        @method('PUT')

        <h2 class="font-display text-lg font-semibold">Contato da empresa</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input type="email" name="contact_email" label="E-mail" :value="$settings['contact_email']" />
            <x-ui.input name="contact_phone" label="Telefone" :value="$settings['contact_phone']" />
            <x-ui.input name="contact_whatsapp" label="WhatsApp" :value="$settings['contact_whatsapp']" placeholder="+55 38991758416" />
            <x-ui.input type="url" name="linkedin_url" label="LinkedIn" :value="$settings['linkedin_url']" />
            <x-ui.input type="url" name="github_url" label="GitHub" :value="$settings['github_url']" />
            <x-ui.input type="url" name="telegram_url" label="Telegram (URL)" :value="$settings['telegram_url']" placeholder="https://t.me/JaderGabriel" />
            <x-ui.input name="telegram_handle" label="Telegram (handle)" :value="$settings['telegram_handle']" placeholder="@JaderGabriel" />
        </div>

        <div class="border-t border-line pt-6">
            <h2 class="font-display text-lg font-semibold">Google Agenda</h2>
            <p class="mt-1 text-sm text-mist">Cole a URL do iframe (ou o HTML completo). Só domínios Google são aceites.</p>
            <x-ui.input type="url" name="google_calendar_url" label="URL da agenda" :value="$settings['google_calendar_url']" class="mt-4" />
            <label class="mt-4 block text-sm">
                <span class="text-mist">Embed (URL ou HTML do iframe)</span>
                <textarea name="google_calendar_embed" rows="5" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 font-mono text-xs" placeholder="https://calendar.google.com/calendar/embed?...">{{ old('google_calendar_embed', $settings['google_calendar_embed']) }}</textarea>
            </label>
        </div>

        <x-ui.button type="submit">Salvar configurações</x-ui.button>
    </form>
@endsection
