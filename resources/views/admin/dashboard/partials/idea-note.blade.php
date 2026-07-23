@php
    $tilts = ['-rotate-1', 'rotate-1', 'rotate-0', '-rotate-2', 'rotate-2'];
    $tilt = $tilts[$index % count($tilts)];
    $color = $note->color?->value ?? 'amber';
@endphp

<article
    id="ideia-{{ $note->id }}"
    class="idea-postit postit postit-{{ $color }} relative flex min-h-[14rem] flex-col p-4 pt-7 shadow-md {{ $tilt }}"
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
            class="idea-postit__title w-full border-0 bg-transparent font-script text-xl leading-tight text-inherit placeholder:opacity-50 focus:outline-none focus:ring-0"
        >

        <textarea
            name="body"
            rows="5"
            placeholder="Escreva a ideia, rascunho ou lembrete…"
            class="idea-postit__body w-full flex-1 resize-none border-0 bg-transparent text-sm leading-relaxed text-inherit placeholder:opacity-50 focus:outline-none focus:ring-0"
        >{{ old('body', $note->body) }}</textarea>

        <div class="mt-auto flex flex-wrap items-center justify-between gap-2 border-t border-black/10 pt-2">
            <div class="idea-postit__colors" role="radiogroup" aria-label="Cor do post-it">
                @foreach($ideaColors as $value => $label)
                    <label class="idea-postit__swatch idea-postit__swatch--{{ $value }}" title="{{ $label }}">
                        <input type="radio" name="color" value="{{ $value }}" class="sr-only" @checked(old('color', $color) === $value)>
                        <span></span>
                    </label>
                @endforeach
            </div>
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
