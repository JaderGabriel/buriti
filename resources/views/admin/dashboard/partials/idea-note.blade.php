@php
    $tilts = ['-rotate-1', 'rotate-1', 'rotate-0', '-rotate-2', 'rotate-2'];
    $tilt = $tilts[$index % count($tilts)];
    $color = $note->color?->value ?? 'amber';
@endphp

<article
    id="ideia-{{ $note->id }}"
    class="idea-postit postit postit-{{ $color }} relative flex min-h-[14rem] flex-col p-4 pt-7 shadow-md {{ $tilt }}"
    data-idea-note
    data-idea-color="{{ $color }}"
    data-color-url="{{ route('admin.idea-notes.color', $note) }}"
>
    <span class="postit-pin" aria-hidden="true"></span>

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
            rows="5"
            placeholder="Escreva a ideia, rascunho ou lembrete…"
            class="idea-postit__body w-full flex-1 resize-none border-0 bg-transparent text-inherit focus:outline-none focus:ring-0"
        >{{ old('body', $note->body) }}</textarea>

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
