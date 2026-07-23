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
            <x-admin.session-list
                :sessions="$sessions"
                :session-driver="$sessionDriver"
                title="Sessões ativas"
                description="Dispositivos com login neste painel. Revogue os que não reconhecer."
                :allow-revoke-current="false"
                :destroy-all-url="count($sessions) > 1 ? route('admin.profile.sessions.destroy-others') : null"
                destroy-all-label="Encerrar outras"
                destroy-all-confirm="Encerrar todas as outras sessões?"
                :destroy-url="fn ($session) => route('admin.profile.sessions.destroy', $session->id)"
            />

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
