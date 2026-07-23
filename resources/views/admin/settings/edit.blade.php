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
                            <li><strong class="text-snow">Nível 3 —</strong> OAuth + API: criar eventos e Meet <em>dentro do CRM</em>, sem abrir o Google Agenda.</li>
                        </ol>
                        <p class="mt-2 text-xs text-mist">
                            API pronta neste ambiente:
                            <span class="{{ $googleApiReady ? 'text-brand-bright' : 'text-mist' }}">{{ $googleApiReady ? 'sim — sync e Meet automáticos' : 'não — complete Client ID/Secret e ligue a conta' }}</span>
                        </p>
                    </div>

                    <h3 class="font-display text-base font-semibold">Dados da Agenda</h3>
                    <p class="text-sm text-mist">Cole a URL do iframe (ou o HTML completo). Só domínios Google são aceites.</p>
                    <x-ui.input type="url" name="google_calendar_url" label="URL da agenda" :value="$settings['google_calendar_url']" class="mt-2" />

                    <div class="mt-4 space-y-2">
                        <label class="block text-sm text-mist">Calendar ID (API) — agenda onde os eventos são criados</label>
                        @if(!empty($googleCalendars))
                            <select name="google_calendar_id" class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-sm">
                                @foreach($googleCalendars as $calendar)
                                    <option value="{{ $calendar['id'] }}" @selected($calendar['selected'] || $calendar['id'] === ($settings['google_calendar_id'] ?? null))>
                                        {{ $calendar['summary'] }}{{ $calendar['primary'] ? ' (primary)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-mist">Agendas com permissão de escrita da conta ligada. ID activo: <code class="break-all text-brand-bright">{{ $googleResolvedCalendarId }}</code></p>
                        @else
                            <input
                                type="text"
                                name="google_calendar_id"
                                value="{{ old('google_calendar_id', $settings['google_calendar_id']) }}"
                                class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 font-mono text-xs"
                                placeholder="primary ou email da agenda"
                            >
                            <p class="text-xs text-mist">
                                Deve coincidir com o <code>src</code> do embed. Actual:
                                <code class="break-all text-brand-bright">{{ $googleResolvedCalendarId ?? $settings['google_calendar_id'] }}</code>
                                @if(($googleIntegration['calendar_matches_embed'] ?? null) === false)
                                    <span class="text-amber-200"> — difere do embed; os eventos podem não aparecer na agenda embutida.</span>
                                @elseif(($googleIntegration['calendar_matches_embed'] ?? null) === true)
                                    <span class="text-brand-bright"> — alinhado com o embed.</span>
                                @endif
                            </p>
                        @endif
                    </div>

                    <label class="mt-4 block text-sm">
                        <span class="text-mist">Embed (URL ou HTML do iframe)</span>
                        <textarea name="google_calendar_embed" rows="5" class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 font-mono text-xs" placeholder="https://calendar.google.com/calendar/embed?...">{{ old('google_calendar_embed', $settings['google_calendar_embed']) }}</textarea>
                    </label>

                    @if(!empty($googleEventColors))
                        <div class="mt-4">
                            <p class="text-sm text-mist">Cores de evento Google (usadas nas tarefas / sync)</p>
                            <div class="gcal-color-picker__swatches mt-2" aria-hidden="true">
                                @foreach($googleEventColors as $color)
                                    <span
                                        class="gcal-color-picker__swatch is-active"
                                        title="{{ $color['label'] }} ({{ $color['id'] }})"
                                        style="--gcal-swatch: {{ $color['background'] }}"
                                    ><span></span></span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <label class="mt-4 flex items-center gap-2 text-sm text-mist">
                        <input type="hidden" name="google_auto_sync" value="0">
                        <input type="checkbox" name="google_auto_sync" value="1" @checked(($settings['google_auto_sync'] ?? '0') === '1') class="rounded border-line">
                        Sincronizar automaticamente ao criar/editar tarefa (requer API nível 3)
                    </label>

                    <div class="mt-6 space-y-3 rounded-2xl border border-line bg-ink/40 p-4">
                        <h3 class="font-display text-base font-semibold">API OAuth (nível 3)</h3>
                        <p class="text-sm text-mist">
                            Preencha as credenciais do Cloud Console. Depois ligue a conta — o Google pede autorização uma vez e grava o refresh token no CRM.
                        </p>

                        @php($connection = $googleConnection ?? ['state' => 'missing_credentials', 'label' => 'Desconhecido', 'message' => '', 'has_secret' => false, 'has_refresh' => false])
                        <div class="rounded-xl border px-3 py-2 text-sm
                            {{ ($connection['state'] ?? '') === 'linked' ? 'border-brand/40 bg-brand/10 text-brand-bright' : 'border-amber-500/40 bg-amber-500/10 text-amber-100' }}">
                            <p class="font-semibold">{{ $connection['label'] }}</p>
                            <p class="mt-1 text-xs opacity-90">{{ $connection['message'] }}</p>
                        </div>

                        <x-ui.input
                            name="google_client_id"
                            label="Client ID"
                            :value="old('google_client_id', $googleClientIdValue)"
                            class="mt-2"
                            placeholder="123456789-xxxxx.apps.googleusercontent.com"
                        />
                        <label class="mt-3 block text-sm">
                            <span class="text-mist">Client Secret {{ !empty($connection['has_secret']) ? '(já guardado — deixe em branco para manter)' : '(obrigatório)' }}</span>
                            <input
                                type="password"
                                name="google_client_secret"
                                value=""
                                autocomplete="new-password"
                                class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-sm"
                                placeholder="{{ !empty($connection['has_secret']) ? '••••••••••••' : 'GOCSPX-...' }}"
                            >
                        </label>
                        <p class="text-xs text-mist">
                            URI de redirecionamento a registar no Google Cloud:
                            <code class="break-all text-brand-bright">{{ $googleRedirectUri }}</code>
                        </p>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            @if(!empty($connection['has_refresh']))
                                <span class="rounded-full bg-brand/15 px-3 py-1 text-xs font-semibold text-brand-bright">Refresh token gravado</span>
                                <button
                                    type="submit"
                                    form="google-test-form"
                                    class="rounded-full border border-brand/40 px-4 py-2 text-xs font-semibold text-brand-bright hover:bg-brand/10"
                                >
                                    Testar ligação
                                </button>
                                <button
                                    type="submit"
                                    form="google-disconnect-form"
                                    class="rounded-full border border-red-500/40 px-4 py-2 text-xs font-semibold text-red-300 hover:bg-red-500/10"
                                >
                                    Desligar conta
                                </button>
                                <a
                                    href="{{ route('admin.google.connect') }}"
                                    class="inline-flex rounded-full border border-line px-4 py-2 text-xs font-semibold text-mist hover:text-snow"
                                >
                                    Religar conta
                                </a>
                            @elseif($googleOauthAppReady)
                                <a
                                    href="{{ route('admin.google.connect') }}"
                                    class="inline-flex rounded-full bg-brand px-4 py-2 text-xs font-semibold text-white hover:bg-brand-bright"
                                >
                                    Ligar conta Google
                                </a>
                            @else
                                <span class="text-xs text-mist">Salve Client ID e Secret para activar «Ligar conta Google».</span>
                            @endif
                        </div>
                    </div>

                    <x-ui.button type="submit" class="mt-2">Salvar configurações</x-ui.button>
                </div>

                @include('admin.settings.partials.google-docs')
            </div>
        </section>
    </form>

    <form id="google-disconnect-form" method="POST" action="{{ route('admin.google.disconnect') }}" class="hidden">
        @csrf
    </form>
    <form id="google-test-form" method="POST" action="{{ route('admin.google.test') }}" class="hidden">
        @csrf
    </form>
@endsection
