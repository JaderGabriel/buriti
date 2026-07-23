@props([
    'attachable',
    'type',
    'kinds' => ['document'],
    'title' => 'Documentos',
    'description' => 'PDF, DOC e outros ficheiros importantes.',
    'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*',
])

@php
    $items = $attachable->relationLoaded('attachments')
        ? $attachable->attachments
        : $attachable->attachments()->get();

    $kindLabels = [
        'document' => 'Documento',
        'media' => 'Mídia',
        'photo' => 'Foto',
    ];
    $showKindSelect = count($kinds) > 1;
    $defaultKind = $kinds[0] ?? 'document';
@endphp

<article {{ $attributes->merge(['class' => 'rounded-sm border border-line bg-panel p-5']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
            @if($description)
                <p class="mt-1 text-xs text-mist">{{ $description }}</p>
            @endif
        </div>
        <span class="rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-mist">
            {{ $items->count() }} ficheiro{{ $items->count() === 1 ? '' : 's' }}
        </span>
    </div>

    <ul class="mt-4 space-y-2">
        @forelse($items as $attachment)
            <li class="flex flex-wrap items-center justify-between gap-3 rounded-sm border border-line/70 px-3 py-2">
                <div class="min-w-0 flex items-center gap-3">
                    @if($attachment->isImage())
                        <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="shrink-0">
                            <img src="{{ $attachment->url() }}" alt="" class="h-10 w-10 rounded-sm object-cover">
                        </a>
                    @else
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-ink/50 text-[10px] font-semibold uppercase tracking-wide text-mist">
                            {{ $attachment->isPdf() ? 'PDF' : 'DOC' }}
                        </span>
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-snow" title="{{ $attachment->title ?: $attachment->original_name }}">
                            {{ $attachment->title ?: $attachment->original_name }}
                        </p>
                        <p class="text-xs text-mist">
                            {{ $kindLabels[$attachment->kind] ?? $attachment->kind }}
                            · {{ $attachment->humanSize() }}
                            · {{ $attachment->created_at->format('d/m/Y') }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.attachments.download', $attachment) }}" class="text-xs text-brand-bright hover:underline">Baixar</a>
                    <form method="POST" action="{{ route('admin.attachments.destroy', $attachment) }}" data-confirm="Remover este ficheiro?">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-mist hover:text-red-300">Remover</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="text-sm text-mist">Nenhum ficheiro nesta pasta.</li>
        @endforelse
    </ul>

    <form method="POST"
          action="{{ route('admin.attachments.store', ['type' => $type, 'id' => $attachable->getKey()]) }}"
          enctype="multipart/form-data"
          class="mt-4 space-y-3 border-t border-line pt-4">
        @csrf
        @unless($showKindSelect)
            <input type="hidden" name="kind" value="{{ $defaultKind }}">
        @else
            <label class="block text-sm">
                <span class="text-mist">Tipo</span>
                <select name="kind" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow">
                    @foreach($kinds as $kind)
                        <option value="{{ $kind }}">{{ $kindLabels[$kind] ?? $kind }}</option>
                    @endforeach
                </select>
            </label>
        @endunless
        <x-ui.input name="title" label="Título (opcional)" :value="old('title')" />
        <label class="block text-sm">
            <span class="text-mist">Ficheiro</span>
            <input type="file"
                   name="file"
                   required
                   accept="{{ $accept }}"
                   class="mt-1.5 w-full text-sm text-mist file:mr-3 file:rounded-sm file:border-0 file:bg-brand file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white">
            @error('file')
                <p class="mt-1 text-xs text-red-400">{{ $message }}</p>
            @enderror
        </label>
        <button type="submit" class="rounded-sm border border-line px-3 py-2 text-sm hover:border-brand-bright/50">
            Adicionar ficheiro
        </button>
    </form>
</article>
