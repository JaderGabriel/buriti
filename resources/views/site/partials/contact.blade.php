<section id="contato" class="border-t border-line py-16 sm:py-24">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-5 lg:grid-cols-[0.9fr_1.1fr] lg:gap-12">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-brand">Contato</p>
            <h2 class="mt-3 font-display text-2xl font-bold sm:text-3xl md:text-4xl">Fale com a empresa</h2>
            <p class="mt-4 text-sm text-mist sm:text-base">Conte o desafio. Respondemos com próximo passo concreto — discovery, proposta ou suporte.</p>

            <x-site.contact-channels
                class="mt-8"
                :email="$contactEmail"
                :phone="$contactPhone"
                :whatsapp="$contactWhatsapp"
            >
                <li class="flex flex-wrap gap-4 pt-2">
                    @if($linkedinUrl ?? false)
                        <a href="{{ $linkedinUrl }}" target="_blank" rel="noopener" class="text-mist hover:text-snow">LinkedIn</a>
                    @endif
                    @if($githubUrl ?? false)
                        <a href="{{ $githubUrl }}" target="_blank" rel="noopener" class="text-mist hover:text-snow">GitHub</a>
                    @endif
                    @if($telegramUrl ?? false)
                        <a href="{{ $telegramUrl }}" target="_blank" rel="noopener" class="text-mist hover:text-snow">{{ $telegramHandle ?? '@JaderGabriel' }}</a>
                    @endif
                </li>
            </x-site.contact-channels>
        </div>

        <div class="rounded-[1.25rem] border border-line bg-panel/80 p-5 sm:rounded-[1.75rem] sm:p-6 md:p-8">
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
                    <x-ui.input name="phone" label="Telefone" :value="old('phone')" />
                    <x-ui.input name="company" label="Empresa" :value="old('company')" />
                </div>
                <x-ui.input name="subject" label="Assunto" :value="old('subject')" required />
                <x-ui.input type="textarea" name="message" label="Mensagem" :value="old('message')" rows="5" required />
                <x-ui.button type="submit" class="w-full sm:w-auto">Enviar mensagem</x-ui.button>
            </form>
        </div>
    </div>
</section>
