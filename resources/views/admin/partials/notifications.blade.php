@php
    $unread = (int) ($adminUnreadNotifications ?? 0);
    $items = $adminRecentNotifications ?? collect();
@endphp

<div
    class="admin-notify"
    x-data="{ open: false }"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
>
    <button
        type="button"
        class="admin-icon-btn admin-notify__btn"
        @click="open = !open"
        :aria-expanded="open.toString()"
        aria-haspopup="true"
        aria-label="Notificações{{ $unread > 0 ? ' ('.$unread.' não lidas)' : '' }}"
        title="Notificações"
    >
        <x-ui.icon name="bell" class="h-5 w-5" />
        @if($unread > 0)
            <span class="admin-notify__count">{{ $unread > 99 ? '99+' : $unread }}</span>
        @endif
    </button>

    <div
        class="admin-notify__panel"
        x-cloak
        x-show="open"
        x-transition
        role="dialog"
        aria-label="Lista de notificações"
    >
        <header class="admin-notify__head">
            <div>
                <p class="admin-notify__eyebrow">Inbox</p>
                <h2 class="admin-notify__title">Notificações</h2>
            </div>
            @if($unread > 0)
                <form method="POST" action="{{ route('account.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="admin-notify__mark">Marcar lidas</button>
                </form>
            @endif
        </header>

        <ul class="admin-notify__list">
            @forelse($items as $item)
                <li>
                    <a
                        href="{{ route('account.notifications.open', $item) }}"
                        @class(['admin-notify__item', 'is-unread' => $item->isUnread()])
                    >
                        <span class="admin-notify__icon" aria-hidden="true">
                            <x-ui.icon :name="$item->type->icon()" class="h-4 w-4" />
                        </span>
                        <span class="admin-notify__body">
                            <span class="admin-notify__item-title">{{ $item->title }}</span>
                            @if($item->body)
                                <span class="admin-notify__item-text">{{ $item->body }}</span>
                            @endif
                            <span class="admin-notify__meta">
                                {{ $item->type->label() }}
                                · {{ $item->created_at?->diffForHumans() }}
                            </span>
                        </span>
                        @if($item->isUnread())
                            <span class="admin-notify__dot" title="Não lida"></span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="admin-notify__empty">Nenhuma notificação ainda.</li>
            @endforelse
        </ul>

        <a href="{{ route('account.notifications.index') }}" class="admin-notify__all">Ver todas</a>
    </div>
</div>
