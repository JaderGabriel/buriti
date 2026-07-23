@props([
    'sessions' => [],
    'title' => 'Sessões ativas',
    'description' => null,
    'showUser' => false,
    'sessionDriver' => null,
    'destroyAllUrl' => null,
    'destroyAllLabel' => 'Revogar todas',
    'destroyAllConfirm' => 'Revogar todas as sessões listadas deste utilizador?',
    /** @var callable|null fn(object $session): string */
    'destroyUrl' => null,
    'allowRevokeCurrent' => true,
])

@php
    $sessionDriver = $sessionDriver ?? config('session.driver');
    $sessions = collect($sessions);
@endphp

<section {{ $attributes->merge(['class' => 'rounded-sm border border-line bg-panel p-5 sm:p-6']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
            <p class="mt-1 text-sm text-mist">
                @if($description)
                    {{ $description }}
                @else
                    IP, local, tipo de dispositivo, aplicativo e última atividade.
                @endif
                @if($sessionDriver !== 'database')
                    <span class="mt-1 block text-xs">
                        Driver atual: <code class="text-brand-bright">{{ $sessionDriver }}</code>
                        — use <code class="text-brand-bright">SESSION_DRIVER=database</code> para listar e revogar.
                    </span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <span class="rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-mist">
                {{ $sessions->count() }} sessão{{ $sessions->count() === 1 ? '' : 'ões' }}
            </span>
            @if($destroyAllUrl && $sessionDriver === 'database' && $sessions->isNotEmpty())
                <form method="POST" action="{{ $destroyAllUrl }}" data-confirm="{{ $destroyAllConfirm }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm font-semibold text-red-300 hover:underline">{{ $destroyAllLabel }}</button>
                </form>
            @endif
        </div>
    </div>

    @if($errors->has('session'))
        <p class="mt-3 text-sm text-red-400">{{ $errors->first('session') }}</p>
    @endif

    <ul class="mt-4 space-y-3">
        @forelse($sessions as $session)
            @php
                $revokeUrl = is_callable($destroyUrl) ? $destroyUrl($session) : null;
                $canRevoke = $revokeUrl && ($allowRevokeCurrent || ! $session->is_current);
            @endphp
            <li class="rounded-sm border border-line px-3 py-3 text-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($showUser && $session->user)
                                <a href="{{ route('admin.users.edit', $session->user) }}" class="font-semibold text-snow hover:text-brand-bright">
                                    {{ $session->user->name }}
                                </a>
                                <span class="text-xs text-mist">{{ '@'.$session->user->username }}</span>
                            @else
                                <p class="font-medium text-snow">
                                    {{ $session->is_current ? 'Esta sessão' : 'Outro dispositivo' }}
                                </p>
                            @endif
                            @if($session->is_current)
                                <span class="rounded-sm bg-brand/15 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand-bright">Atual</span>
                            @endif
                        </div>

                        <dl class="grid gap-1 text-xs text-mist sm:grid-cols-2">
                            <div><dt class="inline text-mist/70">IP:</dt> <dd class="inline font-medium text-snow/90">{{ $session->ip_address ?? '—' }}</dd></div>
                            <div><dt class="inline text-mist/70">Local:</dt> <dd class="inline">{{ $session->location }}</dd></div>
                            <div><dt class="inline text-mist/70">Tipo:</dt> <dd class="inline">{{ $session->device_type }}</dd></div>
                            <div><dt class="inline text-mist/70">App:</dt> <dd class="inline">{{ $session->application }}</dd></div>
                            <div class="sm:col-span-2">
                                <dt class="inline text-mist/70">Data/hora:</dt>
                                <dd class="inline">
                                    {{ $session->last_activity_at->format('d/m/Y H:i:s') }}
                                    <span class="text-mist/70">· {{ $session->last_activity_at->diffForHumans() }}</span>
                                </dd>
                            </div>
                        </dl>
                    </div>

                    @if($canRevoke)
                        <form method="POST" action="{{ $revokeUrl }}" data-confirm="Revogar esta sessão? O utilizador terá de entrar de novo.">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-red-300 hover:underline">Revogar</button>
                        </form>
                    @elseif($session->is_current && ! $allowRevokeCurrent)
                        <span class="text-xs text-mist">Sessão atual</span>
                    @endif
                </div>
            </li>
        @empty
            <li class="text-sm text-mist">
                @if($sessionDriver !== 'database')
                    Nenhuma sessão listável com o driver atual.
                @else
                    Nenhuma sessão ativa no momento.
                @endif
            </li>
        @endforelse
    </ul>
</section>
