@php
    $tilts = ['-rotate-1', 'rotate-1', 'rotate-0', '-rotate-2', 'rotate-2'];
    $tilt = $tilts[$index % count($tilts)];
    $color = $note->color?->value ?? 'amber';
    $ideaCompanies = $ideaCompanies ?? collect();
    $ideaContacts = $ideaContacts ?? collect();
    $selectedCompanyId = old('company_id', $note->company_id);
    $selectedContactId = old('contact_id', $note->contact_id);
    $companyLabel = $note->company?->displayName();
    $contactLabel = $note->contact?->name;
@endphp

<article
    id="ideia-{{ $note->id }}"
    class="idea-postit postit postit-{{ $color }} relative flex min-h-[14rem] flex-col p-4 pt-7 shadow-md {{ $tilt }}"
    data-idea-note
    data-idea-note-id="{{ $note->id }}"
    data-idea-color="{{ $color }}"
    data-color-url="{{ route('admin.idea-notes.color', $note) }}"
>
    <span class="postit-pin" aria-hidden="true"></span>
    <button
        type="button"
        class="idea-postit__drag"
        data-idea-drag
        title="Arrastar para reordenar"
        aria-label="Arrastar post-it"
    >
        ⋮⋮
    </button>

    <form method="POST" action="{{ route('admin.idea-notes.update', $note) }}" class="flex flex-1 flex-col gap-2">
        @csrf
        @method('PUT')

        <input
            type="text"
            name="title"
            value="{{ old('title', $note->title) }}"
            placeholder="Título (opcional)"
            maxlength="180"
            class="idea-postit__title w-full border-0 bg-transparent text-inherit focus:outline-none focus:ring-0"
        >

        <textarea
            name="body"
            rows="4"
            placeholder="Escreva a ideia, rascunho ou lembrete…"
            class="idea-postit__body w-full flex-1 resize-none border-0 bg-transparent text-inherit focus:outline-none focus:ring-0"
        >{{ old('body', $note->body) }}</textarea>

        <div class="idea-postit__refs" data-idea-refs>
            <p class="idea-postit__refs-label">Alocar a</p>

            <div class="idea-postit__chips" data-idea-chips @if(! $companyLabel && ! $contactLabel) hidden @endif>
                <a
                    href="{{ $note->company ? route('admin.companies.show', $note->company) : '#' }}"
                    class="idea-postit__chip idea-postit__chip--company"
                    data-idea-chip="company"
                    @unless($companyLabel) hidden @endunless
                    @if($note->company) data-href="{{ route('admin.companies.show', $note->company) }}" @endif
                >
                    <span data-idea-chip-label>{{ $companyLabel ?: 'Empresa' }}</span>
                </a>
                <a
                    href="{{ $note->contact ? route('admin.contacts.show', $note->contact) : '#' }}"
                    class="idea-postit__chip idea-postit__chip--contact"
                    data-idea-chip="contact"
                    @unless($contactLabel) hidden @endunless
                    @if($note->contact) data-href="{{ route('admin.contacts.show', $note->contact) }}" @endif
                >
                    <span data-idea-chip-label>{{ $contactLabel ?: 'Contato' }}</span>
                </a>
            </div>

            <label class="sr-only" for="idea-company-{{ $note->id }}">Empresa</label>
            <select
                id="idea-company-{{ $note->id }}"
                name="company_id"
                class="idea-postit__select"
                data-idea-company
            >
                <option value="">Sem empresa</option>
                @foreach($ideaCompanies as $company)
                    <option
                        value="{{ $company->id }}"
                        data-label="{{ $company->displayName() }}"
                        data-href="{{ route('admin.companies.show', $company) }}"
                        @selected((string) $selectedCompanyId === (string) $company->id)
                    >
                        {{ $company->displayName() }}
                    </option>
                @endforeach
            </select>

            <label class="sr-only" for="idea-contact-{{ $note->id }}">Contato</label>
            <select
                id="idea-contact-{{ $note->id }}"
                name="contact_id"
                class="idea-postit__select"
                data-idea-contact
            >
                <option value="">Sem contato</option>
                @foreach($ideaContacts as $contact)
                    @php
                        $contactCompanyLabel = $contact->companyLabel();
                    @endphp
                    <option
                        value="{{ $contact->id }}"
                        data-company-id="{{ $contact->company_id ?: '' }}"
                        data-label="{{ $contact->name }}"
                        data-href="{{ route('admin.contacts.show', $contact) }}"
                        @selected((string) $selectedContactId === (string) $contact->id)
                    >
                        {{ $contact->name }}@if($contactCompanyLabel) · {{ $contactCompanyLabel }}@endif
                    </option>
                @endforeach
            </select>
            <p class="idea-postit__refs-hint">Escolha empresa e/ou contato — aparece na ficha correspondente.</p>
        </div>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-black/10 pt-2">
            <div class="idea-postit__colors" role="radiogroup" aria-label="Cor do post-it" data-idea-colors>
                @foreach($ideaColors as $value => $label)
                    <button
                        type="button"
                        class="idea-postit__swatch idea-postit__swatch--{{ $value }}{{ $color === $value ? ' is-active' : '' }}"
                        title="{{ $label }}"
                        aria-label="{{ $label }}"
                        aria-pressed="{{ $color === $value ? 'true' : 'false' }}"
                        data-idea-color-value="{{ $value }}"
                    >
                        <span></span>
                    </button>
                @endforeach
            </div>
            <input type="hidden" name="color" value="{{ $color }}" data-idea-color-input>
            <button type="submit" class="rounded-sm border border-black/15 bg-white/50 px-2.5 py-1 text-xs font-semibold uppercase tracking-wide text-inherit hover:bg-white/80">
                Salvar
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.idea-notes.destroy', $note) }}" class="absolute right-2 top-2" data-confirm="Remover esta ideia?">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-sm px-1.5 py-0.5 text-xs font-semibold opacity-60 hover:bg-black/10 hover:opacity-100" title="Remover" aria-label="Remover ideia">✕</button>
    </form>
</article>
