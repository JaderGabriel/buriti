@if(! empty($href))
    <a href="{{ $href }}" class="admin-user admin-user--chip" title="Perfil" aria-label="Perfil {{ $user->name }}">
@else
    <span class="admin-user admin-user--chip" title="{{ $user->name }}" aria-label="{{ $user->name }}">
@endif
    @if($user->avatarUrl())
        <img src="{{ $user->avatarUrl() }}" alt="" class="admin-user__avatar">
    @else
        <span class="admin-user__avatar admin-user__avatar--fallback">{{ $user->initials() }}</span>
    @endif
    <span class="admin-user__meta">
        <span class="admin-user__name">{{ $user->name }}</span>
        <span class="admin-user__handle">{{ $handle }}</span>
    </span>
@if(! empty($href))
    </a>
@else
    </span>
@endif
