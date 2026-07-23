@props([
    'attachable',
    'type',
    'kinds' => ['document'],
    'layout' => 'auto', // auto | folder | album
    'title' => null,
    'description' => null,
    'accept' => null,
])

@php
    $kinds = array_values($kinds);
    $isAlbum = $layout === 'album' || ($layout === 'auto' && $kinds === ['photo']);
    $layout = $isAlbum ? 'album' : 'folder';

    $kindLabels = [
        'document' => 'Documento',
        'media' => 'Mídia',
        'photo' => 'Foto',
    ];

    $title = $title ?: ($isAlbum ? 'Álbum de fotos' : 'Pasta de arquivos');
    $description = $description ?: ($isAlbum
        ? 'Imagens organizadas neste registro. A foto de perfil continua no bloco acima, quando existir.'
        : 'Arquivos internos deste registro. Itens ocultos ficam na lixeira e podem ser recuperados.');
    $accept = $accept ?: ($isAlbum
        ? 'image/*'
        : '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*,video/*,audio/*');

    $items = $attachable->relationLoaded('attachments')
        ? $attachable->attachments
        : $attachable->attachments()->get();
    $items = $items->whereIn('kind', $kinds)->values();

    $trashed = $attachable->relationLoaded('trashedAttachments')
        ? $attachable->trashedAttachments
        : (method_exists($attachable, 'trashedAttachments') ? $attachable->trashedAttachments()->get() : collect());
    $trashed = $trashed->whereIn('kind', $kinds)->values();

    $showKindSelect = count($kinds) > 1;
    $defaultKind = $kinds[0] ?? 'document';
    $unit = $isAlbum ? 'foto' : 'arquivo';
    $unitPlural = $isAlbum ? 'fotos' : 'arquivos';
    $emptyLabel = $isAlbum ? 'Nenhuma foto neste álbum.' : 'Nenhum arquivo nesta pasta.';
    $uploadLabel = $isAlbum ? 'Enviar foto' : 'Enviar arquivo';
    $fileFieldLabel = $isAlbum ? 'Imagem' : 'Arquivo';
    $eyebrow = $isAlbum ? 'Álbum' : 'Arquivos';
@endphp

<section {{ $attributes->merge(['class' => 'drive-panel'.($isAlbum ? ' drive-panel--album' : ' drive-panel--folder')]) }}>
    <header class="drive-panel__header">
        <div class="drive-panel__heading">
            <span class="drive-panel__icon" aria-hidden="true">
                <x-ui.icon :name="$isAlbum ? 'image' : 'folder'" class="h-4 w-4" />
            </span>
            <div class="min-w-0">
                <p class="drive-panel__eyebrow">{{ $eyebrow }}</p>
                <h2 class="drive-panel__title">{{ $title }}</h2>
                <p class="drive-panel__desc">{{ $description }}</p>
            </div>
        </div>
        <div class="drive-panel__stats">
            <span class="drive-stat">{{ $items->count() }} {{ $items->count() === 1 ? $unit : $unitPlural }}</span>
            @if($trashed->isNotEmpty())
                <span class="drive-stat drive-stat--muted">{{ $trashed->count() }} na lixeira</span>
            @endif
        </div>
    </header>

    <div class="drive-panel__body">
        @if($isAlbum)
            <div class="drive-album">
                @forelse($items as $attachment)
                    <figure class="drive-album__item">
                        <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="drive-album__thumb" title="{{ $attachment->title ?: $attachment->original_name }}">
                            @if($attachment->isImage())
                                <img src="{{ $attachment->url() }}" alt="{{ $attachment->title ?: $attachment->original_name }}">
                            @else
                                <span class="drive-file-badge">{{ $attachment->isPdf() ? 'PDF' : 'DOC' }}</span>
                            @endif
                        </a>
                        <figcaption class="drive-album__meta">
                            <p class="drive-album__name" title="{{ $attachment->title ?: $attachment->original_name }}">
                                {{ $attachment->title ?: $attachment->original_name }}
                            </p>
                            <p class="drive-album__info">{{ $attachment->humanSize() }} · {{ $attachment->created_at->format('d/m/Y') }}</p>
                            <div class="drive-album__actions">
                                <a href="{{ route('admin.attachments.download', $attachment) }}" class="drive-action">Baixar</a>
                                <form method="POST" action="{{ route('admin.attachments.destroy', $attachment) }}" data-confirm="Mover esta foto para a lixeira? Poderá recuperá-la depois.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="drive-action drive-action--danger">Lixeira</button>
                                </form>
                            </div>
                        </figcaption>
                    </figure>
                @empty
                    <div class="drive-empty drive-empty--album">
                        <x-ui.icon name="image" class="h-8 w-8 text-mist" />
                        <p>{{ $emptyLabel }}</p>
                    </div>
                @endforelse
            </div>
        @else
            <div class="drive-folder">
                @forelse($items as $attachment)
                    <article class="drive-row">
                        <div class="drive-row__main">
                            @if($attachment->isImage())
                                <a href="{{ $attachment->url() }}" target="_blank" rel="noopener" class="drive-row__thumb">
                                    <img src="{{ $attachment->url() }}" alt="">
                                </a>
                            @else
                                <span class="drive-file-badge" aria-hidden="true">
                                    {{ $attachment->isPdf() ? 'PDF' : strtoupper(\Illuminate\Support\Str::limit(pathinfo($attachment->original_name, PATHINFO_EXTENSION) ?: 'DOC', 4, '')) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="drive-row__name" title="{{ $attachment->title ?: $attachment->original_name }}">
                                    {{ $attachment->title ?: $attachment->original_name }}
                                </p>
                                <p class="drive-row__meta">
                                    <span class="drive-chip">{{ $kindLabels[$attachment->kind] ?? $attachment->kind }}</span>
                                    <span>{{ $attachment->humanSize() }}</span>
                                    <span>{{ $attachment->created_at->format('d/m/Y H:i') }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="drive-row__actions">
                            <a href="{{ route('admin.attachments.download', $attachment) }}" class="drive-action">
                                <x-ui.icon name="download" class="h-3.5 w-3.5" />
                                Baixar
                            </a>
                            <form method="POST" action="{{ route('admin.attachments.destroy', $attachment) }}" data-confirm="Mover este arquivo para a lixeira? Poderá recuperá-lo depois.">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="drive-action drive-action--danger">
                                    <x-ui.icon name="trash" class="h-3.5 w-3.5" />
                                    Lixeira
                                </button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="drive-empty">
                        <x-ui.icon name="folder" class="h-8 w-8 text-mist" />
                        <p>{{ $emptyLabel }}</p>
                    </div>
                @endforelse
            </div>
        @endif

        <form method="POST"
              action="{{ route('admin.attachments.store', ['type' => $type, 'id' => $attachable->getKey()]) }}"
              enctype="multipart/form-data"
              class="drive-upload">
            @csrf
            <div class="drive-upload__head">
                <p class="drive-upload__label">
                    <x-ui.icon name="upload" class="h-4 w-4" />
                    {{ $uploadLabel }}
                </p>
                <p class="drive-upload__hint">Máx. 10&nbsp;MB. {{ $isAlbum ? 'JPG, PNG, WEBP ou GIF.' : 'PDF, Office, imagens e arquivos compactados.' }}</p>
            </div>

            <div class="drive-upload__fields">
                @unless($showKindSelect)
                    <input type="hidden" name="kind" value="{{ $defaultKind }}">
                @else
                    <label class="drive-field">
                        <span>Categoria</span>
                        <select name="kind" class="drive-control">
                            @foreach($kinds as $kind)
                                <option value="{{ $kind }}">{{ $kindLabels[$kind] ?? $kind }}</option>
                            @endforeach
                        </select>
                    </label>
                @endunless

                <label class="drive-field {{ $showKindSelect ? '' : 'drive-field--grow' }}">
                    <span>Título (opcional)</span>
                    <input type="text" name="title" value="{{ old('title') }}" class="drive-control" placeholder="{{ $isAlbum ? 'Ex.: Evento 2026' : 'Ex.: Contrato assinado' }}">
                </label>

                <label class="drive-field drive-field--grow">
                    <span>{{ $fileFieldLabel }}</span>
                    <input type="file" name="file" required accept="{{ $accept }}" class="drive-file-input">
                    @error('file')
                        <p class="drive-error">{{ $message }}</p>
                    @enderror
                </label>
            </div>

            <div class="drive-upload__footer">
                <button type="submit" class="drive-submit">{{ $uploadLabel }}</button>
            </div>
        </form>

        @if($trashed->isNotEmpty())
            <div class="drive-trash">
                <div class="drive-trash__head">
                    <div>
                        <p class="drive-panel__eyebrow">Lixeira</p>
                        <h3 class="drive-trash__title">Itens ocultos ({{ $trashed->count() }})</h3>
                    </div>
                    <p class="drive-trash__hint">Preservados no servidor até exclusão definitiva.</p>
                </div>
                <ul class="drive-trash__list">
                    @foreach($trashed as $attachment)
                        <li class="drive-trash__item">
                            <div class="min-w-0">
                                <p class="drive-row__name">{{ $attachment->title ?: $attachment->original_name }}</p>
                                <p class="drive-row__meta">
                                    <span>Ocultado {{ $attachment->deleted_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                    @if($attachment->deleter)
                                        <span>{{ $attachment->deleter->name }}</span>
                                    @endif
                                </p>
                            </div>
                            <div class="drive-row__actions">
                                <form method="POST" action="{{ route('admin.attachments.restore', $attachment) }}">
                                    @csrf
                                    <button type="submit" class="drive-action">
                                        <x-ui.icon name="restore" class="h-3.5 w-3.5" />
                                        Recuperar
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.attachments.purge', $attachment) }}" data-confirm="Excluir definitivamente? Esta ação não pode ser desfeita.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="drive-action drive-action--danger">Excluir</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>
