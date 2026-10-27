@extends($layout)

@section('title', 'Notificações — '.$product['name'])

@section('content')
    <div class="notify-inbox">
        <header class="notify-inbox__head">
            <div>
                <p class="notify-inbox__eyebrow">Inbox</p>
                <h1 class="notify-inbox__title">Notificações</h1>
                <p class="notify-inbox__lead">Avisos do Jirau, mensagens do site e lembretes da agenda — no mesmo lugar do perfil.</p>
            </div>
            @if($unreadCount > 0)
                <form method="POST" action="{{ route('account.notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="notify-inbox__mark">Marcar todas como lidas</button>
                </form>
            @endif
        </header>

        <ul class="notify-inbox__list">
            @forelse($notifications as $item)
                <li>
                    <a
                        href="{{ route('account.notifications.open', $item) }}"
                        @class(['notify-inbox__item', 'is-unread' => $item->isUnread()])
                    >
                        <span class="notify-inbox__icon" aria-hidden="true">
                            <x-ui.icon :name="$item->type->icon()" class="h-5 w-5" />
                        </span>
                        <span class="notify-inbox__body">
                            <span class="notify-inbox__row">
                                <span class="notify-inbox__item-title">{{ $item->title }}</span>
                                <time class="notify-inbox__time">{{ $item->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</time>
                            </span>
                            @if($item->body)
                                <span class="notify-inbox__text">{{ $item->body }}</span>
                            @endif
                            <span class="notify-inbox__meta">{{ $item->type->label() }}</span>
                        </span>
                        @if($item->isUnread())
                            <span class="notify-inbox__dot" title="Não lida"></span>
                        @endif
                    </a>
                </li>
            @empty
                <li class="notify-inbox__empty">Nenhuma notificação ainda.</li>
            @endforelse
        </ul>

        @if($notifications->hasPages())
            <div class="notify-inbox__pager">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
@endsection
