@extends('layouts.admin')

@section('content')
    @php
        $isOpen = $selected !== null;
        $phoneDigits = $selected?->phone ? preg_replace('/\D+/', '', $selected->phone) : null;
    @endphp

    <div class="messenger-shell -mx-4 -mb-6 sm:-mx-6 md:-mx-8 md:-mb-8">
        <div @class([
            'messenger',
            'messenger--open' => $isOpen,
        ])>
            {{-- Lista de conversas --}}
            <aside class="messenger-list" aria-label="Lista de mensagens">
                <header class="messenger-list__header">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-brand-bright">Caixa de entrada</p>
                        <h1 class="mt-1 font-display text-xl font-bold text-snow">Mensagens</h1>
                    </div>
                    @if($unreadCount > 0)
                        <span class="messenger-badge">{{ $unreadCount }} nova{{ $unreadCount === 1 ? '' : 's' }}</span>
                    @endif
                </header>

                <div class="messenger-list__scroll">
                    @forelse($messages as $message)
                        <a
                            href="{{ route('admin.messages.show', $message) }}"
                            @class([
                                'messenger-item',
                                'messenger-item--active' => $selected?->is($message),
                                'messenger-item--unread' => $message->isUnread(),
                            ])
                        >
                            <span class="messenger-avatar" aria-hidden="true">{{ $message->initials() }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-baseline justify-between gap-2">
                                    <span class="truncate font-semibold text-snow">{{ $message->name }}</span>
                                    <span class="shrink-0 text-[11px] text-mist">{{ $message->relativeDay() }}</span>
                                </span>
                                <span class="mt-0.5 block truncate text-xs text-mist">{{ $message->subject }}</span>
                                <span class="mt-0.5 block truncate text-sm text-mist/90">{{ $message->preview() }}</span>
                            </span>
                            @if($message->isUnread())
                                <span class="messenger-dot" title="Não lida"></span>
                            @endif
                        </a>
                    @empty
                        <div class="px-5 py-16 text-center text-sm text-mist">
                            Nenhuma mensagem recebida ainda.
                        </div>
                    @endforelse
                </div>

                @if($messages->hasPages())
                    <div class="messenger-list__footer">
                        {{ $messages->links() }}
                    </div>
                @endif
            </aside>

            {{-- Painel da conversa --}}
            <section class="messenger-chat" aria-label="Conversa">
                @if($selected)
                    <header class="messenger-chat__header">
                        <div class="flex min-w-0 items-center gap-3">
                            <a href="{{ route('admin.messages.index') }}" class="messenger-back lg:hidden" aria-label="Voltar à lista">←</a>
                            <span class="messenger-avatar messenger-avatar--lg" aria-hidden="true">{{ $selected->initials() }}</span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-snow">{{ $selected->name }}</p>
                                <p class="truncate text-xs text-mist">
                                    {{ $selected->email }}
                                    @if($selected->company) · {{ $selected->company }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <a href="mailto:{{ $selected->email }}?subject={{ rawurlencode('Re: '.$selected->subject) }}" class="messenger-icon-btn" title="E-mail">
                                <x-ui.icon name="mail" class="h-4 w-4" />
                            </a>
                            @if($phoneDigits)
                                <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="messenger-icon-btn" title="WhatsApp">
                                    <x-ui.icon name="whatsapp" class="h-4 w-4" />
                                </a>
                                <a href="tel:{{ preg_replace('/\s+/', '', $selected->phone) }}" class="messenger-icon-btn" title="Ligar">
                                    <x-ui.icon name="phone" class="h-4 w-4" />
                                </a>
                            @endif
                            <form method="POST" action="{{ route('admin.messages.destroy', $selected) }}" data-confirm="Remover esta mensagem?">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="messenger-icon-btn messenger-icon-btn--danger" title="Remover">
                                    <x-ui.icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </header>

                    <div class="messenger-chat__thread">
                        <div class="messenger-day-divider">
                            <span>{{ $selected->created_at->format('d/m/Y') }}</span>
                        </div>

                        <div class="messenger-meta-chip">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-mist">Assunto</p>
                            <p class="mt-0.5 text-sm text-snow">{{ $selected->subject }}</p>
                            @if($selected->preferred_channel || $selected->phone)
                                <p class="mt-2 text-xs text-mist">
                                    @if($selected->preferred_channel)
                                        Canal: <span class="uppercase tracking-wide text-snow/80">{{ $selected->preferred_channel }}</span>
                                    @endif
                                    @if($selected->phone)
                                        @if($selected->preferred_channel) · @endif
                                        Tel: {{ $selected->phone }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <article class="messenger-bubble">
                            <p class="whitespace-pre-wrap leading-relaxed text-snow">{{ $selected->message }}</p>
                            <footer class="messenger-bubble__time">
                                {{ $selected->created_at->format('H:i') }}
                                @unless($selected->isUnread())
                                    <span class="text-brand-bright">✓✓</span>
                                @endunless
                            </footer>
                        </article>
                    </div>

                    <footer class="messenger-composer">
                        <div class="messenger-composer__actions">
                            <a href="mailto:{{ $selected->email }}?subject={{ rawurlencode('Re: '.$selected->subject) }}" class="messenger-composer__primary">
                                Responder por e-mail
                            </a>
                            @if($phoneDigits)
                                <a href="https://wa.me/{{ $phoneDigits }}" target="_blank" rel="noopener" class="messenger-composer__secondary">
                                    WhatsApp
                                </a>
                            @endif
                            @if($selected->contact)
                                <a href="{{ route('admin.contacts.show', $selected->contact) }}" class="messenger-composer__secondary">
                                    Contato CRM
                                </a>
                            @else
                                <form method="POST" action="{{ route('admin.messages.link-contact', $selected) }}">
                                    @csrf
                                    <button type="submit" class="messenger-composer__secondary">
                                        Vincular contato
                                    </button>
                                </form>
                            @endif
                        </div>
                        <p class="mt-2 text-[11px] text-mist">Mensagem recebida pelo formulário do site — responda pelos canais acima.</p>
                    </footer>
                @else
                    <div class="messenger-empty">
                        <div class="messenger-empty__card">
                            <span class="messenger-avatar messenger-avatar--xl" aria-hidden="true">
                                <x-ui.icon name="message" class="h-7 w-7" />
                            </span>
                            <h2 class="mt-4 font-display text-xl font-semibold text-snow">Mensageria BURI-TI</h2>
                            <p class="mt-2 max-w-sm text-sm text-mist">
                                Selecione uma conversa à esquerda para ler a mensagem do site, como no Telegram ou WhatsApp.
                            </p>
                        </div>
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
