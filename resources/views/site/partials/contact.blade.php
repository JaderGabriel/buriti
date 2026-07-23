<section id="contato" class="section-shell">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="cta-band mb-10 overflow-hidden rounded-sm border px-6 py-8 sm:px-8 sm:py-10 md:px-10">
            <p class="section-kicker">Próximo passo</p>
            <h2 class="mt-3 font-display text-2xl font-bold text-snow sm:text-3xl md:text-4xl">Pronto para o próximo passo digital?</h2>
            <p class="mt-3 max-w-2xl text-sm text-mist sm:text-base">
                Construa o futuro digital com a BURI-TI — proposta objetiva, escopo claro e acompanhamento próximo.
            </p>
        </div>

        <div class="grid gap-12 lg:grid-cols-[0.95fr_1.05fr]">
            <div>
                <p class="section-kicker">Contato comercial</p>
                <h3 class="section-title">Vamos estruturar sua próxima entrega de TI</h3>
                <p class="mt-4 text-sm text-mist sm:text-base">
                    Deixe nome, e-mail, telefone e o melhor canal. Respondemos com o próximo passo: discovery, proposta ou suporte.
                </p>

                <div class="mt-8">
                    <x-site.contact-icons
                        :email="$contactEmail"
                        :phone="$contactPhone"
                        :whatsapp="$contactWhatsapp"
                        :linkedin="$linkedinUrl ?? null"
                        :github="$githubUrl ?? null"
                        :telegram="$telegramUrl ?? null"
                        :telegram-handle="$telegramHandle ?? null"
                    />
                </div>
            </div>

            <div class="rounded-sm border border-line bg-panel p-5 sm:p-7 md:p-8">
                <x-ui.flash :message="session('contact_success')" />

                <form method="POST" action="{{ route('contact.store') }}" class="relative space-y-4">
                    @csrf
                    <div class="sr-only" aria-hidden="true">
                        <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="name" label="Nome" :value="old('name')" required />
                        <x-ui.input type="email" name="email" label="E-mail" :value="old('email')" required />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <span class="block text-sm text-mist">Telefone / WhatsApp</span>
                            <div
                                class="mt-1.5 grid gap-2 sm:grid-cols-[minmax(0,14rem)_1fr]"
                                x-data="buritiPhoneCountryField(@js(config('countries')), @js(old('phone_country', 'BR')))"
                            >
                                <label class="block text-sm">
                                    <span class="sr-only">País (DDI)</span>
                                    <select
                                        name="phone_country"
                                        required
                                        x-model="iso"
                                        class="w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1"
                                    >
                                        @foreach(config('countries') as $country)
                                            <option value="{{ $country['iso'] }}" @selected(old('phone_country', 'BR') === $country['iso'])>
                                                {{ $country['flag'] }} {{ $country['name'] }} (+{{ $country['dial'] }})
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-sm">
                                    <span class="sr-only">Número</span>
                                    <div class="flex overflow-hidden rounded-sm border border-line bg-ink focus-within:ring-1 focus-within:ring-brand-bright">
                                        <span class="inline-flex items-center gap-1.5 border-r border-line px-3 text-sm text-mist" x-text="dialLabel()"></span>
                                        <input
                                            type="tel"
                                            name="phone_number"
                                            value="{{ old('phone_number') }}"
                                            required
                                            inputmode="numeric"
                                            autocomplete="tel-national"
                                            placeholder="99999-9999"
                                            class="min-w-0 flex-1 bg-transparent px-3 py-2.5 text-snow outline-none"
                                        >
                                    </div>
                                </label>
                            </div>
                            @if(isset($errors) && ($errors->has('phone') || $errors->has('phone_number') || $errors->has('phone_country')))
                                <span class="mt-1 block text-xs text-red-400">
                                    {{ $errors->first('phone_number') ?: ($errors->first('phone_country') ?: $errors->first('phone')) }}
                                </span>
                            @endif
                            <p class="mt-1.5 text-xs text-mist">Escolha o país pela bandeira/nome. Digite só o DDD e o número, sem o DDI.</p>
                        </div>
                        <x-ui.input name="company" label="Empresa" :value="old('company')" class="sm:col-span-2" />
                    </div>

                    <label class="block text-sm">
                        <span class="text-mist">Canal preferido de contato</span>
                        <select name="preferred_channel" required class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1">
                            <option value="phone" @selected(old('preferred_channel', 'phone') === 'phone')>Telefone</option>
                            <option value="whatsapp" @selected(old('preferred_channel') === 'whatsapp')>WhatsApp</option>
                            <option value="email" @selected(old('preferred_channel') === 'email')>E-mail</option>
                        </select>
                        @if(isset($errors) && $errors->has('preferred_channel'))
                            <span class="mt-1 block text-xs text-red-400">{{ $errors->first('preferred_channel') }}</span>
                        @endif
                    </label>

                    <x-ui.input name="subject" label="Assunto / necessidade" :value="old('subject')" required placeholder="Ex.: Painel BI + integração i-Educar" />
                    <x-ui.input type="textarea" name="message" label="Mensagem" :value="old('message')" rows="5" required />

                    <label class="flex items-start gap-3 rounded-sm border border-line bg-ink/20 px-3 py-3 text-sm text-mist">
                        <input type="checkbox" name="privacy_consent" value="1" class="mt-1" @checked(old('privacy_consent')) required>
                        <span>
                            Li e aceito a
                            <a href="{{ route('privacy') }}" target="_blank" rel="noopener" class="font-semibold text-brand-bright hover:underline">Política de Privacidade</a>
                            e autorizo o tratamento dos dados para retorno comercial (LGPD).
                        </span>
                    </label>
                    @error('privacy_consent')
                        <p class="text-xs text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-xs text-mist">Usamos o telefone para retorno comercial rápido. Sem spam.</p>
                        <x-ui.button type="submit" class="w-full sm:w-auto">Enviar pedido de contato</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
