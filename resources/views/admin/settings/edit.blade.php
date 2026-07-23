@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Configurações</h1>
        <p class="mt-1 text-mist">Contatos públicos e integração Google Agenda / Meet</p>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <section class="max-w-3xl space-y-4 rounded-2xl border border-line bg-panel p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-semibold">Contato da empresa</h2>
                    <p class="mt-1 text-sm text-mist">Aparecem no site (rodapé, formulário e canais).</p>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-ui.input type="email" name="contact_email" label="E-mail" :value="$settings['contact_email']" />
                <x-ui.input name="contact_phone" label="Telefone" :value="$settings['contact_phone']" />
                <x-ui.input name="contact_whatsapp" label="WhatsApp" :value="$settings['contact_whatsapp']" placeholder="+55 38 99175-8416" />
                <x-ui.input type="url" name="linkedin_url" label="LinkedIn" :value="$settings['linkedin_url']" />
                <x-ui.input type="url" name="github_url" label="GitHub" :value="$settings['github_url']" />
                <x-ui.input type="url" name="telegram_url" label="Telegram (URL)" :value="$settings['telegram_url']" placeholder="https://t.me/JaderGabriel" />
                <x-ui.input name="telegram_handle" label="Telegram (handle)" :value="$settings['telegram_handle']" placeholder="@JaderGabriel" />
            </div>
            <x-admin.inline-docs title="Canais públicos" class="mt-2">
                <p>Use o formato internacional no WhatsApp/telefone (ex.: <code>+55 38 99175-8416</code>) para os botões <code>wa.me</code> e <code>tel:</code> funcionarem no site.</p>
                <p class="admin-docs__note mb-0">O handle do Telegram alimenta o botão de contacto; a URL completa é usada nos ícones sociais.</p>
            </x-admin.inline-docs>
        </section>

        <section id="google-integration" class="scroll-mt-6">
            <div class="settings-google-layout">
                <div class="space-y-4 rounded-2xl border border-line bg-panel p-5 sm:p-6">
                    <div class="rounded-2xl border border-brand/30 bg-brand/5 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="font-display text-lg font-semibold">Integração Google</h2>
                            <span class="rounded-full bg-brand/15 px-3 py-1 text-xs font-semibold text-brand-bright">{{ $googleIntegration['label'] }}</span>
                        </div>
                        <p class="mt-2 text-sm text-mist">{{ $googleIntegration['next_step'] }}</p>
                        <ol class="mt-4 space-y-2 text-sm text-mist">
                            <li><strong class="text-snow">Nível 1 —</strong> URL da Agenda + atalhos Meet nas tarefas.</li>
                            <li><strong class="text-snow">Nível 2 —</strong> Embed público da Agenda no painel de tarefas.</li>
                            <li><strong class="text-snow">Nível 3 —</strong> API OAuth no <code class="text-brand-bright">.env</code> para criar eventos e Meet automaticamente.</li>
                        </ol>
                        <p class="mt-2 text-xs text-mist">
                            API pronta neste ambiente:
                            <span class="{{ $googleApiReady ? 'text-brand-bright' : 'text-mist' }}">{{ $googleApiReady ? 'sim (credenciais detetadas)' : 'não — preencha GOOGLE_CLIENT_ID / SECRET / REFRESH_TOKEN' }}</span>
                        </p>
                    </div>

                    <h3 class="font-display text-base font-semibold">Dados da Agenda</h3>
                    <p class="text-sm text-mist">Cole a URL do iframe (ou o HTML completo). Só domínios Google são aceites.</p>
                    <x-ui.input type="url" name="google_calendar_url" label="URL da agenda" :value="$settings['google_calendar_url']" class="mt-2" />
                    <x-ui.input name="google_calendar_id" label="Calendar ID (API)" :value="$settings['google_calendar_id']" class="mt-4" placeholder="primary ou email da agenda" />
                    <label class="mt-4 block text-sm">
                        <span class="text-mist">Embed (URL ou HTML do iframe)</span>
                        <textarea name="google_calendar_embed" rows="5" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 font-mono text-xs" placeholder="https://calendar.google.com/calendar/embed?...">{{ old('google_calendar_embed', $settings['google_calendar_embed']) }}</textarea>
                    </label>
                    <label class="mt-4 flex items-center gap-2 text-sm text-mist">
                        <input type="hidden" name="google_auto_sync" value="0">
                        <input type="checkbox" name="google_auto_sync" value="1" @checked(($settings['google_auto_sync'] ?? '0') === '1') class="rounded border-line">
                        Sincronizar automaticamente ao criar/editar tarefa (requer API nível 3)
                    </label>

                    <x-ui.button type="submit" class="mt-2">Salvar configurações</x-ui.button>
                </div>

                @include('admin.settings.partials.google-docs')
            </div>
        </section>
    </form>
@endsection
