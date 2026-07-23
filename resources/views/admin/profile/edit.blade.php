@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Meu perfil</h1>
        <p class="mt-1 text-mist">Foto, dados da conta, senha e sessões ativas — cada bloco salva por conta própria.</p>
    </div>

    <div class="grid gap-8 xl:grid-cols-[1.1fr_0.9fr]">
        <div class="space-y-6">
            <form
                method="POST"
                action="{{ route('admin.profile.avatar') }}"
                enctype="multipart/form-data"
                class="rounded-sm border border-line bg-panel p-5 sm:p-6"
            >
                @csrf
                @method('PUT')

                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Foto de perfil</p>
                <p class="mt-1 text-sm text-mist">Atualize só a imagem — não é preciso mexer nos dados abaixo.</p>

                <div class="mt-4">
                    <x-admin.avatar-field
                        :url="$user->avatarUrl()"
                        :initials="$user->initials()"
                        size="sm"
                        input-id="profile-avatar"
                    >
                        <p class="text-sm text-mist">Arquivo (JPG/PNG até 2&nbsp;MB)</p>
                    </x-admin.avatar-field>
                </div>

                @error('avatar')
                    <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                @enderror

                @if($user->avatar_path)
                    <label class="mt-3 flex items-center gap-2 text-sm text-mist">
                        <input type="checkbox" name="remove_avatar" value="1" class="rounded border-line">
                        Remover foto atual
                    </label>
                @endif

                <div class="mt-4">
                    <x-ui.button type="submit">Salvar foto</x-ui.button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.profile.update') }}" class="space-y-4 rounded-sm border border-line bg-panel p-5 sm:p-6">
                @csrf
                @method('PUT')

                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Dados da conta</p>

                <x-ui.input name="name" label="Nome" :value="old('name', $user->name)" required />
                <x-ui.input name="username" label="Username" :value="old('username', $user->username)" required placeholder="ex.: jadergabriel" />
                <x-ui.input type="email" name="email" label="E-mail" :value="old('email', $user->email)" required />

                <div class="space-y-4 border-t border-line pt-4">
                    <p class="text-sm font-semibold text-snow">Alterar senha</p>
                    <x-ui.input type="password" name="current_password" label="Senha atual" autocomplete="current-password" />
                    <x-admin.password-fields :editing="true" class="border-0 pt-0" />
                </div>

                <x-ui.button type="submit">Salvar dados da conta</x-ui.button>
            </form>
        </div>

        <div class="space-y-6">
            <section class="rounded-sm border border-line bg-panel p-5 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-lg font-semibold">Sessões ativas</h2>
                        <p class="mt-1 text-sm text-mist">
                            Driver atual: <code class="text-brand-bright">{{ $sessionDriver }}</code>
                            @if($sessionDriver !== 'database')
                                — use <code class="text-brand-bright">SESSION_DRIVER=database</code> no .env para listar e revogar.
                            @endif
                        </p>
                    </div>
                    @if($sessionDriver === 'database' && count($sessions) > 1)
                        <form method="POST" action="{{ route('admin.profile.sessions.destroy-others') }}" data-confirm="Encerrar todas as outras sessões?">
                            @csrf
                            @method('DELETE')
                            <button class="text-sm font-semibold text-brand-bright hover:underline">Encerrar outras</button>
                        </form>
                    @endif
                </div>

                @if($errors->has('session'))
                    <p class="mt-3 text-sm text-red-400">{{ $errors->first('session') }}</p>
                @endif

                <ul class="mt-4 space-y-3">
                    @forelse($sessions as $session)
                        <li class="rounded-sm border border-line px-3 py-3 text-sm">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-medium text-snow">
                                        {{ $session->is_current ? 'Esta sessão' : 'Outro dispositivo' }}
                                    </p>
                                    <p class="mt-1 text-xs text-mist">IP: {{ $session->ip_address ?? '—' }}</p>
                                    <p class="mt-1 break-all text-xs text-mist">{{ \Illuminate\Support\Str::limit($session->user_agent, 90) }}</p>
                                    <p class="mt-1 text-xs text-mist">Atividade: {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p>
                                </div>
                                @unless($session->is_current)
                                    <form method="POST" action="{{ route('admin.profile.sessions.destroy', $session->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs text-red-300 hover:underline">Encerrar</button>
                                    </form>
                                @endunless
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-mist">Nenhuma sessão listável no momento.</li>
                    @endforelse
                </ul>
            </section>

            <section class="rounded-sm border border-line bg-panel p-5 sm:p-6">
                <h2 class="font-display text-lg font-semibold">Histórico de login</h2>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse($loginActivities as $activity)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-b border-line py-2">
                            <span class="{{ $activity->successful ? 'text-brand-bright' : 'text-red-300' }}">
                                {{ $activity->successful ? 'Sucesso' : 'Falha' }}
                            </span>
                            <span class="text-xs text-mist">{{ $activity->ip_address }} · {{ $activity->created_at?->format('d/m/Y H:i') }}</span>
                        </li>
                    @empty
                        <li class="text-mist">Sem registros ainda.</li>
                    @endforelse
                </ul>
            </section>
        </div>
    </div>
@endsection
