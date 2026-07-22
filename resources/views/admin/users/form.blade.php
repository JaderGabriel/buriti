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

                <div class="border-b border-line bg-[linear-gradient(135deg,rgba(199,70,52,0.12),transparent_55%)] px-5 py-6 sm:px-7">
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                        <div class="shrink-0">
                            <template x-if="preview">
                                <img :src="preview" alt="" class="h-20 w-20 rounded-sm object-cover ring-1 ring-line sm:h-24 sm:w-24">
                            </template>
                            <template x-if="!preview">
                                <span class="inline-flex h-20 w-20 items-center justify-center rounded-sm border border-brand/40 bg-ink/40 font-display text-2xl font-bold text-brand-bright sm:h-24 sm:w-24 sm:text-3xl">{{ $user->initials() ?: '?' }}</span>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-snow">Foto de perfil</p>
                            <p class="mt-1 text-xs text-mist">JPG ou PNG até 2&nbsp;MB. Salve aqui sem alterar os dados abaixo.</p>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-mist transition hover:border-brand-bright/40 hover:text-snow">
                                <span x-text="fileName || 'Escolher arquivo'">Escolher arquivo</span>
                                <input type="file" name="avatar" accept="image/*" class="sr-only" @change="onFile($event)">
                            </label>
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
                    </div>
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
              class="overflow-hidden rounded-sm border border-line bg-panel"
              x-data="passwordGenerator">
            @csrf
            @if($editing) @method('PUT') @endif

            @unless($editing)
                <div
                    class="border-b border-line bg-[linear-gradient(135deg,rgba(199,70,52,0.12),transparent_55%)] px-5 py-6 sm:px-7"
                    x-data="{
                        preview: null,
                        fileName: '',
                        onFile(event) {
                            const file = event.target.files?.[0];
                            this.fileName = file?.name || '';
                            if (this.preview) URL.revokeObjectURL(this.preview);
                            this.preview = file ? URL.createObjectURL(file) : null;
                        }
                    }"
                >
                    <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                        <div class="shrink-0">
                            <template x-if="preview">
                                <img :src="preview" alt="" class="h-20 w-20 rounded-sm object-cover ring-1 ring-line sm:h-24 sm:w-24">
                            </template>
                            <template x-if="!preview">
                                <span class="inline-flex h-20 w-20 items-center justify-center rounded-sm border border-brand/40 bg-ink/40 font-display text-2xl font-bold text-brand-bright sm:h-24 sm:w-24 sm:text-3xl">?</span>
                            </template>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-snow">Foto de perfil</p>
                            <p class="mt-1 text-xs text-mist">Opcional. Será salva ao criar o usuário.</p>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-mist transition hover:border-brand-bright/40 hover:text-snow">
                                <span x-text="fileName || 'Escolher arquivo'">Escolher arquivo</span>
                                <input type="file" name="avatar" accept="image/*" class="sr-only" @change="onFile($event)">
                            </label>
                            @error('avatar')
                                <p class="mt-2 text-xs text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
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

                <div class="border-t border-line pt-6">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.14em] text-mist">Senha</p>
                            <p class="mt-1 text-xs text-mist">
                                {{ $editing ? 'Deixe em branco para manter a senha atual.' : 'Obrigatória na criação. Pode gerar uma senha forte.' }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-sm border border-line px-3 py-2 text-xs font-semibold text-snow transition hover:border-brand-bright/50"
                                @click="generate()"
                            >
                                Gerar senha aleatória
                            </button>
                            <button
                                type="button"
                                class="rounded-sm border border-line px-3 py-2 text-xs font-semibold text-mist transition hover:border-brand-bright/50 hover:text-snow"
                                @click="copy()"
                                :disabled="!password"
                                x-text="copied ? 'Copiada!' : 'Copiar'"
                            ></button>
                        </div>
                    </div>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="text-mist">{{ $editing ? 'Nova senha (opcional)' : 'Senha' }}</span>
                            <div class="relative mt-1.5">
                                <input
                                    :type="visible ? 'text' : 'password'"
                                    name="password"
                                    x-model="password"
                                    @required(! $editing)
                                    autocomplete="new-password"
                                    class="w-full rounded-xl border border-line bg-ink px-3 py-2.5 pr-20 text-snow outline-none ring-brand-bright focus:ring-1"
                                >
                                <button
                                    type="button"
                                    class="absolute inset-y-0 right-0 px-3 text-xs font-semibold text-mist hover:text-snow"
                                    @click="visible = !visible"
                                    x-text="visible ? 'Ocultar' : 'Mostrar'"
                                ></button>
                            </div>
                            @if(isset($errors) && $errors->has('password'))
                                <span class="mt-1 block text-xs text-red-400">{{ $errors->first('password') }}</span>
                            @endif
                        </label>

                        <label class="block text-sm">
                            <span class="text-mist">Confirmar senha</span>
                            <input
                                :type="visible ? 'text' : 'password'"
                                name="password_confirmation"
                                x-model="confirmation"
                                @required(! $editing)
                                autocomplete="new-password"
                                class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 text-snow outline-none ring-brand-bright focus:ring-1"
                            >
                        </label>
                    </div>

                    <p x-show="generated" x-cloak class="mt-3 rounded-sm border border-brand/30 bg-brand/10 px-3 py-2 text-xs text-mist">
                        Senha gerada e preenchida nos dois campos. Guarde-a num sítio seguro antes de salvar — depois só o hash fica no sistema.
                    </p>
                </div>

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
