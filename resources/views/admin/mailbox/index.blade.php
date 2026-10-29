@extends('layouts.admin')

@section('content')
    @php
        $ready = (bool) ($workspace['ready'] ?? false);
        $folders = $workspace['folders'] ?? [];
        $folder = $workspace['folder'] ?? 'INBOX';
        $listing = $workspace['listing'] ?? ['messages' => [], 'exists' => 0, 'page' => 1, 'pages' => 1];
        $selected = $workspace['selected'] ?? null;
        $identity = $workspace['identity'] ?? ['address' => '', 'name' => '', 'signature' => ''];
        $error = $workspace['error'] ?? null;
        $isOpen = $selected !== null || $mode === 'compose';
        $messages = $listing['messages'] ?? [];
        $folderMeta = collect($folders)->firstWhere('name', $folder) ?? ['label' => $folder, 'role' => 'other', 'unseen' => 0, 'messages' => $listing['exists'] ?? 0];
        $isSent = ($folderMeta['role'] ?? '') === 'sent';
        $inboxUnseen = (int) (collect($folders)->firstWhere('role', 'inbox')['unseen'] ?? 0);
        $composeMode = $compose['modo'] ?? 'new';
        $composeTitle = match ($composeMode) {
            'reply' => 'Responder',
            'reply-all' => 'Responder a todos',
            'forward' => 'Encaminhar',
            'contact-reply' => 'Resposta ao site',
            default => 'Nova mensagem',
        };
        $folderIcon = fn (string $role) => match ($role) {
            'inbox' => 'mail',
            'sent' => 'delivery',
            'drafts' => 'pencil',
            'trash' => 'trash',
            'junk' => 'lost',
            'archive' => 'folder',
            default => 'folder',
        };
        $showCc = filled(old('cc', $compose['cc'] ?? ''));
        $showBcc = filled(old('bcc', $compose['bcc'] ?? ''));
        $catalog = $catalog ?? ['labels' => collect(), 'markers' => [], 'projects' => collect(), 'jiraus' => collect(), 'ideas' => collect()];
        $mailList = function (array $extra = []) use ($folder, $query): array {
            return array_filter([
                'pasta' => $folder,
                'buscar' => $query !== '' ? $query : null,
                ...$extra,
            ], fn ($value) => $value !== null && $value !== '');
        };
    @endphp

    <div class="messenger-shell mail-shell">
        @if($error)
            <x-ui.flash :message="$error" type="error" />
        @endif

        @if(! $ready && $mode !== 'compose')
            <article class="mail-setup">
                <p class="mail-kicker">Comercial · E-mail</p>
                <h1 class="mt-2 font-display text-2xl font-bold">E-mail da conta</h1>
                <p class="mt-3 text-sm text-mist">
                    Liga SMTP e IMAP em Sistema → MAIL. A mesma identidade serve para o painel, as respostas do site e o bot no Telegram
                    (<code>/email</code> e <code>/mensagem responder</code>).
                </p>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="{{ route('admin.settings.mail') }}" class="pm-btn pm-btn--primary">
                        <x-ui.icon name="settings" class="h-4 w-4" />
                        Configurar conta
                    </a>
                    <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--ghost">
                        <x-ui.icon name="pencil" class="h-4 w-4" />
                        Escrever mesmo assim
                    </a>
                </div>
            </article>
        @else
            <div @class(['messenger', 'mail', 'messenger--open' => $isOpen])>
                <aside class="mail-folders" aria-label="Pastas">
                    <div class="mail-folders__head">
                        <p class="mail-kicker">E-mail</p>
                        <p class="mt-1 truncate text-sm font-semibold text-snow" title="{{ $identity['address'] }}">{{ $identity['name'] ?: $identity['address'] ?: 'Conta' }}</p>
                        <p class="truncate text-[11px] text-mist" title="{{ $identity['address'] }}">{{ $identity['address'] ?: 'sem identidade' }}</p>
                    </div>
                    <nav class="mail-folders__nav">
                        @foreach($folders as $item)
                            <a
                                href="{{ route('admin.mailbox.index', ['pasta' => $item['name']]) }}"
                                @class(['mail-folder', 'is-active' => $folder === $item['name']])
                            >
                                <span class="mail-folder__label">
                                    <x-ui.icon :name="$folderIcon($item['role'] ?? 'other')" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                </span>
                                @if(($item['unseen'] ?? 0) > 0)
                                    <span class="messenger-badge">{{ $item['unseen'] }}</span>
                                @elseif(($item['messages'] ?? 0) > 0)
                                    <span class="mail-folder__count">{{ $item['messages'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                    <form method="POST" action="{{ route('admin.mailbox.folders.store') }}" class="mail-folders__create">
                        @csrf
                        <input type="text" name="name" placeholder="Nova pasta" maxlength="80" required class="mail-input" autocomplete="off">
                        <button type="submit" class="messenger-icon-btn" title="Criar pasta">
                            <x-ui.icon name="plus" class="h-4 w-4" />
                        </button>
                    </form>
                    <a href="{{ route('admin.settings.mail') }}" class="mail-folders__settings">
                        <x-ui.icon name="settings" class="h-3.5 w-3.5" />
                        Conta SMTP/IMAP
                    </a>
                </aside>

                <aside class="messenger-list" aria-label="Mensagens" x-data="{ selected: [] }">
                    <header class="messenger-list__header">
                        <div class="min-w-0">
                            <p class="mail-kicker">{{ $folderMeta['label'] ?? $folder }}</p>
                            <h1 class="mt-1 font-display text-xl font-bold text-snow">
                                {{ ($listing['exists'] ?? 0) }} mensagem{{ ($listing['exists'] ?? 0) === 1 ? '' : 's' }}
                                @if(($folderMeta['unseen'] ?? 0) > 0)
                                    <span class="messenger-badge">{{ $folderMeta['unseen'] }} nova{{ $folderMeta['unseen'] === 1 ? '' : 's' }}</span>
                                @endif
                            </h1>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <a href="{{ route('admin.mailbox.index', $mailList()) }}" class="messenger-icon-btn" title="Atualizar">
                                <x-ui.icon name="restore" class="h-4 w-4" />
                            </a>
                            <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--primary" title="Escrever">
                                <x-ui.icon name="pencil" class="h-4 w-4" />
                                Escrever
                            </a>
                        </div>
                    </header>
                    <form
                        method="GET"
                        action="{{ route('admin.mailbox.index') }}"
                        class="mail-search"
                        role="search"
                        data-mail-search
                        aria-label="Procurar nesta pasta"
                    >
                        <input type="hidden" name="pasta" value="{{ $folder }}">
                        <label class="sr-only" for="mail-folder-search">Procurar nesta pasta</label>
                        <span class="mail-search__icon" aria-hidden="true">
                            <x-ui.icon name="search" class="h-4 w-4" />
                        </span>
                        <input
                            id="mail-folder-search"
                            type="text"
                            name="buscar"
                            value="{{ $query }}"
                            placeholder="Remetente, contacto, destinatário ou assunto"
                            class="mail-input mail-search__input"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            enterkeyhint="search"
                            data-mail-search-input
                        >
                        <a
                            href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}"
                            @class(['mail-search__clear', 'is-idle' => $query === ''])
                            title="Mostrar todas as mensagens"
                            aria-label="Limpar busca desta pasta"
                            data-mail-search-clear
                        >
                            <x-ui.icon name="lost" class="h-3.5 w-3.5" />
                        </a>
                    </form>
                    <form id="mail-bulk" method="POST" action="{{ route('admin.mailbox.bulk') }}" class="mail-bulk" x-show="selected.length > 0" x-cloak>
                        @csrf
                        <input type="hidden" name="pasta" value="{{ $folder }}">
                        <p class="mail-bulk__count"><span x-text="selected.length"></span> seleccionada(s)</p>
                        <div class="mail-bulk__actions">
                            <button type="submit" name="acao" value="star" class="messenger-icon-btn" title="Estrela">
                                <x-ui.icon name="star" class="h-4 w-4" />
                            </button>
                            <button type="submit" name="acao" value="unstar" class="messenger-icon-btn" title="Tirar estrela">☆</button>
                            <button type="submit" name="acao" value="seen" class="messenger-icon-btn" title="Marcar lida">
                                <x-ui.icon name="eye" class="h-4 w-4" />
                            </button>
                            <button type="submit" name="acao" value="unseen" class="messenger-icon-btn" title="Marcar não lida">
                                <x-ui.icon name="eye-off" class="h-4 w-4" />
                            </button>
                            <label class="mail-bulk__select">
                                <span class="sr-only">Marcador</span>
                                <select
                                    name="marker"
                                    class="mail-input mail-input--compact"
                                    data-mail-auto-submit
                                    data-mail-acao="marker"
                                >
                                    <option value="">Marcador</option>
                                    @foreach($catalog['markers'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="mail-bulk__select">
                                <span class="sr-only">Etiqueta</span>
                                <select
                                    name="label_id"
                                    class="mail-input mail-input--compact"
                                    data-mail-auto-submit
                                    data-mail-acao="label"
                                >
                                    <option value="">Etiqueta</option>
                                    @foreach($catalog['labels'] as $label)
                                        <option value="{{ $label->id }}">{{ $label->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if(count($folders) > 1)
                                <label class="mail-bulk__select">
                                    <span class="sr-only">Mover</span>
                                    <select name="destino" class="mail-input mail-input--compact">
                                        @foreach($folders as $item)
                                            @if($item['name'] !== $folder)
                                                <option value="{{ $item['name'] }}">{{ $item['label'] }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button type="submit" name="acao" value="move" class="mail-bulk__go">Mover</button>
                                </label>
                            @endif
                            <button type="submit" name="acao" value="destroy" class="messenger-icon-btn messenger-icon-btn--danger" title="Apagar" onclick="return confirm('Enviar as seleccionadas para o Lixo?')">
                                <x-ui.icon name="trash" class="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                    <div class="messenger-list__scroll">
                        @forelse($messages as $item)
                            @php
                                $peer = $isSent ? ($item['to'] ?: $item['from']) : ($item['from'] ?: $item['to']);
                            @endphp
                            <div
                                @class([
                                    'messenger-item',
                                    'messenger-item--active' => ($selected['uid'] ?? null) === $item['uid'],
                                    'messenger-item--unread' => empty($item['seen']),
                                ])
                            >
                                <label class="mail-check">
                                    <span class="sr-only">Seleccionar mensagem</span>
                                    <input type="checkbox" form="mail-bulk" name="uids[]" value="{{ $item['uid'] }}" x-model="selected">
                                </label>
                                <a
                                    href="{{ route('admin.mailbox.index', $mailList(['uid' => $item['uid'], 'page' => $listing['page'] ?? 1])) }}"
                                    class="mail-item-link"
                                >
                                    <span class="messenger-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($peer ?: '?', 0, 1)) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="flex items-baseline justify-between gap-2">
                                            <span class="truncate font-semibold text-snow">{{ $peer ?: '(sem remetente)' }}</span>
                                            <span class="shrink-0 text-[11px] text-mist">{{ \App\Support\MailDate::compact($item['date'] ?? '') }}</span>
                                        </span>
                                        <span class="mt-0.5 flex items-center gap-1.5">
                                            @if(! empty($item['answered']))
                                                <x-ui.icon name="restore" class="h-3 w-3 shrink-0 text-brand-bright" />
                                            @endif
                                            @if(! empty($item['starred']) || ! empty($item['flagged']))
                                                <x-ui.icon name="star" class="h-3 w-3 shrink-0 text-brand-bright" />
                                            @endif
                                            @if(! empty($item['marker_label']))
                                                <span class="mail-marker mail-marker--{{ $item['marker'] }}">{{ $item['marker_label'] }}</span>
                                            @endif
                                            <span class="truncate text-xs {{ empty($item['seen']) ? 'text-snow' : 'text-mist' }}">{{ ($item['subject'] ?? '') !== '' ? $item['subject'] : '(sem assunto)' }}</span>
                                        </span>
                                        @if(! empty($item['labels']))
                                            <span class="mail-label-row">
                                                @foreach($item['labels'] as $chip)
                                                    <span class="mail-label mail-label--{{ $chip['color'] }}">{{ $chip['name'] }}</span>
                                                @endforeach
                                            </span>
                                        @endif
                                    </span>
                                    @if(empty($item['seen']))
                                        <span class="messenger-dot" title="Não lida"></span>
                                    @endif
                                </a>
                            </div>
                        @empty
                            <div class="px-5 py-16 text-center text-sm text-mist">
                                @if($query !== '')
                                    <p>Nada encontrado para “{{ $query }}”.</p>
                                    <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="mt-3 inline-block text-brand-bright hover:text-snow">Mostrar todas as mensagens</a>
                                @else
                                    Nenhuma mensagem nesta pasta.
                                @endif
                            </div>
                        @endforelse
                    </div>
                    @if(($listing['pages'] ?? 1) > 1)
                        <div class="messenger-list__footer flex items-center justify-between text-xs text-mist">
                            <a href="{{ route('admin.mailbox.index', $mailList(['page' => max(1, ($listing['page'] ?? 1) - 1)])) }}" class="{{ ($listing['page'] ?? 1) <= 1 ? 'pointer-events-none opacity-40' : 'hover:text-snow' }}">← Anterior</a>
                            <span>{{ $listing['page'] }} / {{ $listing['pages'] }}</span>
                            <a href="{{ route('admin.mailbox.index', $mailList(['page' => min($listing['pages'], ($listing['page'] ?? 1) + 1)])) }}" class="{{ ($listing['page'] ?? 1) >= ($listing['pages'] ?? 1) ? 'pointer-events-none opacity-40' : 'hover:text-snow' }}">Seguinte →</a>
                        </div>
                    @endif
                </aside>

                <section
                    @class(['messenger-chat', 'mail-compose-pane' => $mode === 'compose'])
                    aria-label="{{ $mode === 'compose' ? $composeTitle : 'Leitura' }}"
                >
                    @if($mode === 'compose')
                        <header class="messenger-chat__header">
                            <div class="flex min-w-0 items-center gap-3">
                                <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="messenger-back lg:hidden" aria-label="Voltar">←</a>
                                <div class="min-w-0">
                                    <p class="mail-kicker">{{ $composeTitle }}</p>
                                    <p class="truncate font-semibold text-snow">De {{ $identity['name'] ?: $identity['address'] ?: 'a conta gravada' }}</p>
                                </div>
                            </div>
                            <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="messenger-icon-btn" title="Fechar">
                                <x-ui.icon name="lost" class="h-4 w-4" />
                            </a>
                        </header>
                        <form
                            method="POST"
                            action="{{ route('admin.mailbox.send') }}"
                            class="mail-compose"
                            enctype="multipart/form-data"
                            x-data="mailCompose"
                            data-contacts-url="{{ route('admin.mailbox.contacts', absolute: false) }}"
                            data-show-cc="{{ $showCc ? '1' : '0' }}"
                            data-show-bcc="{{ $showBcc ? '1' : '0' }}"
                            data-to="{{ old('to', $compose['to'] ?? '') }}"
                            data-cc="{{ old('cc', $compose['cc'] ?? '') }}"
                            data-bcc="{{ old('bcc', $compose['bcc'] ?? '') }}"
                            @submit="beforeSubmit($event)"
                        >
                            @csrf
                            <input type="hidden" name="in_reply_to" value="{{ $compose['in_reply_to'] ?? '' }}">
                            <input type="hidden" name="reply_folder" value="{{ $compose['reply_folder'] ?? '' }}">
                            <input type="hidden" name="reply_uid" value="{{ $compose['reply_uid'] ?? '' }}">
                            <input type="hidden" name="contact_message_id" value="{{ $compose['contact_message_id'] ?? '' }}">
                            <div class="mail-compose__scroll">
                                <div class="mail-compose__row">
                                    <div class="mail-compose__heading">
                                        <label for="mail-to">Para</label>
                                        <div class="mail-compose__toggles">
                                            <button type="button" class="mail-compose__toggle" @click="showCc = !showCc" :class="{ 'is-on': showCc }" :aria-pressed="showCc">Cc</button>
                                            <button type="button" class="mail-compose__toggle" @click="showBcc = !showBcc" :class="{ 'is-on': showBcc }" :aria-pressed="showBcc">Cco</button>
                                        </div>
                                    </div>
                                    @include('admin.mailbox.partials.recipient-field', [
                                        'field' => 'to',
                                        'id' => 'mail-to',
                                        'placeholder' => 'Nome, empresa ou e-mail',
                                    ])
                                </div>
                                <div class="mail-compose__row" x-show="showCc" x-cloak>
                                    <label for="mail-cc">Cc</label>
                                    @include('admin.mailbox.partials.recipient-field', [
                                        'field' => 'cc',
                                        'id' => 'mail-cc',
                                        'placeholder' => 'Nome, empresa ou e-mail',
                                    ])
                                </div>
                                <div class="mail-compose__row" x-show="showBcc" x-cloak>
                                    <label for="mail-bcc">Cco</label>
                                    @include('admin.mailbox.partials.recipient-field', [
                                        'field' => 'bcc',
                                        'id' => 'mail-bcc',
                                        'placeholder' => 'Nome, empresa ou e-mail',
                                    ])
                                </div>
                                <div class="mail-compose__row">
                                    <label for="mail-subject">Assunto</label>
                                    <input id="mail-subject" type="text" name="subject" value="{{ old('subject', $compose['subject'] ?? '') }}" required class="mail-input" autocomplete="off">
                                </div>
                                @include('admin.mailbox.partials.editor')
                            </div>
                            <div class="mail-compose__bar">
                                <label class="mail-compose__sign">
                                    <input type="hidden" name="signature" value="0">
                                    <input type="checkbox" name="signature" value="1" @checked(old('signature', '1') === '1')>
                                    Assinatura
                                </label>
                                <div class="messenger-composer__actions">
                                    <button type="submit" name="draft" value="1" class="messenger-composer__secondary" :disabled="sending">
                                        <span x-show="! sending">Rascunho</span>
                                        <span x-show="sending" x-cloak>A guardar…</span>
                                    </button>
                                    <button type="submit" class="messenger-composer__primary" :disabled="sending">
                                        <span x-show="! sending">Enviar</span>
                                        <span x-show="sending" x-cloak>A enviar…</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <details class="mail-signature">
                            <summary>Assinatura da conta</summary>
                            <p class="mail-signature__hint">Padrão comercial: logomarca, nome e ícones de telefone, WhatsApp e Telegram (clicáveis, sem mostrar o número). O aviso LGPD entra sempre no rodapé.</p>
                            <div class="mail-signature__preview">
                                @include('mail.partials.signature', ['block' => $identity['signature_block'] ?? []])
                            </div>
                            <form method="POST" action="{{ route('admin.mailbox.signature') }}" class="mail-signature__form">
                                @csrf
                                @method('PUT')
                                <textarea name="mail_signature" rows="3" class="mail-input" placeholder="Jader Gabriel&#10;BURI-TI - Consultoria &amp; Software">{{ old('mail_signature', $identity['signature']) }}</textarea>
                                <button type="submit" class="messenger-composer__secondary justify-self-start">Guardar nome na assinatura</button>
                            </form>
                        </details>
                    @elseif($selected)
                        <header class="messenger-chat__header">
                            <div class="flex min-w-0 items-center gap-3">
                                <a href="{{ route('admin.mailbox.index', ['pasta' => $folder]) }}" class="messenger-back lg:hidden" aria-label="Voltar">←</a>
                                <span class="messenger-avatar messenger-avatar--lg">{{ mb_strtoupper(mb_substr($selected['from'] ?: '?', 0, 1)) }}</span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-snow">{{ $selected['subject'] }}</p>
                                    <p class="truncate text-xs text-mist">{{ $selected['from'] }}</p>
                                </div>
                            </div>
                            <div class="mail-read-actions">
                                <form method="POST" action="{{ route('admin.mailbox.bulk') }}">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uids[]" value="{{ $selected['uid'] }}">
                                    <input type="hidden" name="acao" value="{{ ! empty($selected['starred']) || ! empty($selected['flagged']) ? 'unstar' : 'star' }}">
                                    <button type="submit" class="messenger-icon-btn {{ ! empty($selected['starred']) || ! empty($selected['flagged']) ? 'is-on' : '' }}" title="{{ ! empty($selected['starred']) || ! empty($selected['flagged']) ? 'Tirar estrela' : 'Estrela' }}">
                                        <x-ui.icon name="star" class="h-4 w-4" />
                                    </button>
                                </form>
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'reply', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Responder">
                                    <x-ui.icon name="restore" class="h-4 w-4" />
                                    <span>Responder</span>
                                </a>
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'reply-all', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Responder a todos">
                                    <x-ui.icon name="users" class="h-4 w-4" />
                                    <span>Todos</span>
                                </a>
                                <a href="{{ route('admin.mailbox.compose', ['modo' => 'forward', 'responder_pasta' => $folder, 'responder_uid' => $selected['uid']]) }}" class="mail-action" title="Encaminhar">
                                    <x-ui.icon name="delivery" class="h-4 w-4" />
                                    <span>Encaminhar</span>
                                </a>
                                <form method="POST" action="{{ route('admin.mailbox.seen') }}">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <input type="hidden" name="seen" value="{{ $selected['seen'] ? 0 : 1 }}">
                                    <button type="submit" class="messenger-icon-btn" title="{{ $selected['seen'] ? 'Marcar não lida' : 'Marcar lida' }}">
                                        <x-ui.icon :name="$selected['seen'] ? 'eye-off' : 'eye'" class="h-4 w-4" />
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.mailbox.destroy') }}" onsubmit="return confirm('Enviar para o Lixo?')">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <button type="submit" class="messenger-icon-btn messenger-icon-btn--danger" title="Apagar">
                                        <x-ui.icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </header>
                        <div class="messenger-chat__thread mail-thread">
                            <dl class="mail-meta">
                                <div><dt>De</dt><dd>{{ $selected['from'] ?: '—' }}</dd></div>
                                <div><dt>Para</dt><dd>{{ $selected['to'] ?: '—' }}</dd></div>
                                @if($selected['cc'])
                                    <div><dt>Cc</dt><dd>{{ $selected['cc'] }}</dd></div>
                                @endif
                                <div><dt>Data</dt><dd>{{ \App\Support\MailDate::full($selected['date'] ?? '') }}</dd></div>
                            </dl>
                            @if($selected['html'])
                                <iframe class="mail-html" sandbox srcdoc="{{ $selected['html'] }}" title="Corpo da mensagem"></iframe>
                            @else
                                <div class="mail-text">{{ $selected['text'] !== '' ? $selected['text'] : '(sem conteúdo)' }}</div>
                            @endif
                            @if($selected['attachments'] !== [])
                                <ul class="mail-attachments">
                                    @foreach($selected['attachments'] as $file)
                                        <li>
                                            <a href="{{ route('admin.mailbox.attachment', ['pasta' => $folder, 'uid' => $selected['uid'], 'i' => $file['index']]) }}" class="mail-chip">
                                                <x-ui.icon name="download" class="h-3.5 w-3.5" />
                                                {{ $file['name'] }}
                                                <span>{{ number_format($file['size'] / 1024, 1) }} KB</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(count($folders) > 1)
                                <form method="POST" action="{{ route('admin.mailbox.move') }}" class="mail-move">
                                    @csrf
                                    <input type="hidden" name="pasta" value="{{ $folder }}">
                                    <input type="hidden" name="uid" value="{{ $selected['uid'] }}">
                                    <label class="text-xs text-mist">Mover para
                                        <select name="destino" class="mail-input mt-1">
                                            @foreach($folders as $item)
                                                @if($item['name'] !== $folder)
                                                    <option value="{{ $item['name'] }}">{{ $item['label'] }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="messenger-composer__secondary mt-2">Mover</button>
                                </form>
                            @endif
                            <section class="mail-crm" aria-label="CRM interno">
                                <p class="mail-kicker">CRM interno</p>
                                <p class="mt-1 text-xs text-mist">Estrelas, etiquetas e vínculos com projetos, jiraus e ideias. Não aparece no site público.</p>
                                @php
                                    $annotateFields = [
                                        'pasta' => $folder,
                                        'uid' => $selected['uid'],
                                        'message_id' => $selected['message_id'] ?? '',
                                        'subject' => $selected['subject'] ?? '',
                                        'from' => $selected['from'] ?? '',
                                    ];
                                @endphp
                                <div class="mail-crm__row">
                                    <form method="POST" action="{{ route('admin.mailbox.marker') }}" class="mail-crm__form">
                                        @csrf
                                        @foreach($annotateFields as $name => $value)
                                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                        @endforeach
                                        <label>
                                            Marcador
                                            <select
                                                name="marker"
                                                class="mail-input mt-1"
                                                data-mail-auto-submit
                                                data-allow-empty="1"
                                            >
                                                <option value="">—</option>
                                                @foreach($catalog['markers'] as $value => $label)
                                                    <option value="{{ $value }}" @selected(($selected['marker'] ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </form>
                                    <form method="POST" action="{{ route('admin.mailbox.label') }}" class="mail-crm__form">
                                        @csrf
                                        @foreach($annotateFields as $name => $value)
                                            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                        @endforeach
                                        <label>
                                            Etiqueta
                                            <select name="label_id" class="mail-input mt-1" data-mail-auto-submit>
                                                <option value="">Escolher…</option>
                                                @foreach($catalog['labels'] as $label)
                                                    <option value="{{ $label->id }}">{{ $label->name }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </form>
                                </div>
                                @if(! empty($selected['labels']))
                                    <ul class="mail-label-row mt-2">
                                        @foreach($selected['labels'] as $chip)
                                            <li>
                                                <form method="POST" action="{{ route('admin.mailbox.label') }}" class="inline">
                                                    @csrf
                                                    @foreach($annotateFields as $name => $value)
                                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                                    @endforeach
                                                    <input type="hidden" name="label_id" value="{{ $chip['id'] }}">
                                                    <input type="hidden" name="detach_label" value="1">
                                                    <button type="submit" class="mail-label mail-label--{{ $chip['color'] }}" title="Remover etiqueta">
                                                        {{ $chip['name'] }} ×
                                                    </button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                                <form method="POST" action="{{ route('admin.mailbox.label') }}" class="mail-crm__form mt-3">
                                    @csrf
                                    <label>
                                        Nova etiqueta
                                        <input type="text" name="name" maxlength="40" class="mail-input mt-1" placeholder="Nome">
                                    </label>
                                    <select name="color" class="mail-input">
                                        @foreach(\App\Models\MailLabel::COLORS as $color)
                                            <option value="{{ $color }}">{{ $color }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="messenger-composer__secondary">Criar</button>
                                </form>
                                <form method="POST" action="{{ route('admin.mailbox.link') }}" class="mail-crm__form mt-4" x-data="{ kind: 'project' }">
                                    @csrf
                                    @foreach($annotateFields as $name => $value)
                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                    @endforeach
                                    <label>
                                        Ligar a
                                        <select name="kind" class="mail-input mt-1" required x-model="kind">
                                            <option value="project">Projeto</option>
                                            <option value="jirau">Jirau</option>
                                            <option value="idea">Ideia</option>
                                        </select>
                                    </label>
                                    <label x-show="kind === 'project'">
                                        Projeto
                                        <select name="linkable_id" class="mail-input mt-1" :disabled="kind !== 'project'">
                                            @foreach($catalog['projects'] as $project)
                                                <option value="{{ $project->id }}">{{ $project->name }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label x-show="kind === 'jirau'" x-cloak>
                                        Jirau
                                        <select name="linkable_id" class="mail-input mt-1" :disabled="kind !== 'jirau'">
                                            @foreach($catalog['jiraus'] as $jirau)
                                                <option value="{{ $jirau->id }}">{{ $jirau->name }}{{ $jirau->project ? ' · '.$jirau->project->name : '' }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label x-show="kind === 'idea'" x-cloak>
                                        Ideia
                                        <select name="linkable_id" class="mail-input mt-1" :disabled="kind !== 'idea'">
                                            @foreach($catalog['ideas'] as $idea)
                                                <option value="{{ $idea->id }}">{{ $idea->displayTitle() }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <button type="submit" class="messenger-composer__secondary">Ligar</button>
                                </form>
                                @if(! empty($selected['links']))
                                    <ul class="mail-link-list">
                                        @foreach($selected['links'] as $link)
                                            <li>
                                                <span class="mail-kicker">{{ $link['kind_label'] }}</span>
                                                @if($link['href'])
                                                    <a href="{{ $link['href'] }}" class="text-brand-bright hover:text-snow">{{ $link['name'] }}</a>
                                                @else
                                                    <span>{{ $link['name'] }}</span>
                                                @endif
                                                <form method="POST" action="{{ route('admin.mailbox.unlink') }}" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    @foreach($annotateFields as $name => $value)
                                                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                                                    @endforeach
                                                    <input type="hidden" name="link_id" value="{{ $link['id'] }}">
                                                    <button type="submit" class="mail-unlink" title="Desligar">×</button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        </div>
                    @else
                        <div class="messenger-empty">
                            <div class="messenger-empty__card">
                                <span class="messenger-avatar messenger-avatar--xl"><x-ui.icon name="mail" class="h-7 w-7" /></span>
                                <h2 class="mt-4 font-display text-lg font-semibold">E-mail da conta</h2>
                                <p class="mt-2 max-w-sm text-sm text-mist">
                                    Escolhe uma mensagem à esquerda ou escreve uma nova. O envio sai autenticado como {{ $identity['address'] ?: 'a conta gravada' }}{{ $inboxUnseen > 0 ? ' · '.$inboxUnseen.' por ler na caixa de entrada' : '' }}.
                                </p>
                                <div class="mt-5 flex flex-wrap justify-center gap-2">
                                    <a href="{{ route('admin.mailbox.compose') }}" class="pm-btn pm-btn--primary">
                                        <x-ui.icon name="pencil" class="h-4 w-4" />
                                        Escrever
                                    </a>
                                    <a href="{{ route('admin.messages.index') }}" class="pm-btn pm-btn--ghost">
                                        Mensagens do site
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </div>
@endsection
