@extends('layouts.admin')

@section('content')
    @php $editing = $user->exists; @endphp
    <div class="mb-8">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-mist transition hover:text-snow">← Usuários</a>
        <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-brand">{{ $editing ? 'Editar' : 'Criar' }}</p>
        <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">{{ $editing ? 'Editar usuário' : 'Novo usuário' }}</h1>
        <p class="mt-1 text-sm text-mist">
            {{ $editing ? 'Foto e dados da conta são salvos em separado.' : 'Defina identidade, foto e credenciais de acesso ao painel.' }}
        </p>
    </div>

    <div class="max-w-3xl space-y-6">
        @if($editing)
            <form
                method="POST"
                action="{{ route('admin.users.avatar', $user) }}"
                enctype="multipart/form-data"
                class="overflow-hidden rounded-sm border border-line bg-panel"
            >
                @csrf
                @method('PUT')

                <div class="border-b border-line bg-[linear-gradient(135deg,rgba(199,70,52,0.12),transparent_55%)] px-5 py-6 sm:px-7">
                    <x-admin.avatar-field
                        :url="$user->avatarUrl()"
                        :initials="$user->initials() ?: '?'"
                        input-id="user-avatar-{{ $user->id }}"
                    >
                        <p class="text-sm font-medium text-snow">Foto de perfil</p>
                        <p class="mt-1 text-xs text-mist">JPG ou PNG até 2&nbsp;MB. Salve aqui sem alterar os dados abaixo.</p>
                    </x-admin.avatar-field>

                    @error('avatar')
                        <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    @if($user->avatar_path)
                        <label class="mt-3 flex items-center gap-2 text-sm text-mist">
                            <input type="checkbox" name="remove_avatar" value="1" class="rounded border-line">
                            Remover foto atual
                        </label>
                    @endif
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 bg-ink/20 px-5 py-4 sm:px-7">
                    <p class="text-xs text-mist">Só atualiza a imagem do perfil.</p>
                    <x-ui.button type="submit">Salvar foto</x-ui.button>
                </div>
            </form>
        @endif

        <form method="POST"
              action="{{ $editing ? route('admin.users.update', $user) : route('admin.users.store') }}"
              @if(! $editing) enctype="multipart/form-data" @endif
              class="overflow-hidden rounded-sm border border-line bg-panel">
            @csrf
            @if($editing) @method('PUT') @endif

            @unless($editing)
                <div class="border-b border-line bg-[linear-gradient(135deg,rgba(199,70,52,0.12),transparent_55%)] px-5 py-6 sm:px-7">
                    <x-admin.avatar-field
                        :url="null"
                        initials="?"
                        input-id="user-avatar-new"
                    >
                        <p class="text-sm font-medium text-snow">Foto de perfil</p>
                        <p class="mt-1 text-xs text-mist">Opcional. Será salva ao criar o usuário.</p>
                    </x-admin.avatar-field>
                    @error('avatar')
                        <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>
            @endunless

            <div class="space-y-6 px-5 py-6 sm:px-7">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Identidade</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="name" label="Nome" :value="old('name', $user->name)" required />
                        <x-ui.input name="username" label="Username" :value="old('username', $user->username)" required placeholder="ex.: jadergabriel" />
                        <div class="sm:col-span-2">
                            <x-ui.input type="email" name="email" label="E-mail" :value="old('email', $user->email)" required />
                        </div>
                    </div>
                </div>

                <x-admin.password-fields :editing="$editing" />

                <div class="border-t border-line pt-6">
                    <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Permissões</p>
                    <label class="mt-4 flex items-start gap-3 rounded-sm border border-line bg-ink/30 px-4 py-3 text-sm text-mist transition hover:border-brand-bright/30">
                        <input type="hidden" name="is_admin" value="0">
                        <input type="checkbox" name="is_admin" value="1" class="mt-1" @checked(old('is_admin', $user->is_admin ?? true))>
                        <span>
                            <strong class="text-snow">Administrador</strong>
                            <span class="mt-0.5 block text-xs">Pode acessar o painel e gerenciar usuários, CRM e projetos.</span>
                        </span>
                    </label>
                    @if(isset($errors) && $errors->has('is_admin'))
                        <p class="mt-2 text-xs text-red-400">{{ $errors->first('is_admin') }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line bg-ink/20 px-5 py-4 sm:px-7">
                <a href="{{ route('admin.users.index') }}" class="text-sm text-mist hover:text-snow">Cancelar</a>
                <x-ui.button type="submit">{{ $editing ? 'Salvar dados da conta' : 'Criar usuário' }}</x-ui.button>
            </div>
        </form>
    </div>
@endsection
