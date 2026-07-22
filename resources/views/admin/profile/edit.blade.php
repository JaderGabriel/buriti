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
                x-data="{
                    preview: @js($user->avatarUrl()),
                    fileName: '',
                    onFile(event) {
                        const file = event.target.files?.[0];
                        this.fileName = file?.name || '';
                        if (this.preview && this.preview.startsWith('blob:')) {
                            URL.revokeObjectURL(this.preview);
                        }
                        this.preview = file ? URL.createObjectURL(file) : @js($user->avatarUrl());
                    }
                }"
            >
                @csrf
                @method('PUT')

                <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Foto de perfil</p>
                <p class="mt-1 text-sm text-mist">Atualize só a imagem — não é preciso mexer nos dados abaixo.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="shrink-0">
                        <template x-if="preview">
                            <img :src="preview" alt="" class="h-16 w-16 rounded-sm object-cover ring-1 ring-line">
                        </template>
                        <template x-if="!preview">
                            <span class="inline-flex h-16 w-16 items-center justify-center rounded-sm border border-line text-lg font-semibold text-brand-bright">{{ $user->initials() }}</span>
                        </template>
                    </div>
                    <label class="block min-w-0 flex-1 text-sm">
                        <span class="text-mist">Arquivo (JPG/PNG até 2&nbsp;MB)</span>
                        <input
                            type="file"
                            name="avatar"
                            accept="image/*"
                            class="mt-1.5 w-full text-mist file:mr-3 file:rounded-sm file:border-0 file:bg-brand file:px-4 file:py-2 file:text-sm file:text-white"
                            @change="onFile($event)"
                        >
                        <span class="mt-1 block text-xs text-mist" x-text="fileName" x-show="fileName" x-cloak></span>
                    </label>
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

                <div class="border-t border-line pt-4">
                    <p class="mb-3 text-sm font-semibold text-snow">Alterar senha</p>
                    <div class="space-y-3">
                        <x-ui.input type="password" name="current_password" label="Senha atual" autocomplete="current-password" />
                        <x-ui.input type="password" name="password" label="Nova senha" autocomplete="new-password" />
                        <x-ui.input type="password" name="password_confirmation" label="Confirmar nova senha" autocomplete="new-password" />
                    </div>
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
