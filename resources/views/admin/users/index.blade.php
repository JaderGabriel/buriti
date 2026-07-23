@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand">Acesso</p>
            <h1 class="mt-2 font-display text-2xl font-bold sm:text-3xl">Usuários</h1>
            <p class="mt-1 text-mist">Criar, editar, desativar ou remover contas do painel</p>
        </div>
        <x-ui.button :href="route('admin.users.create')">Criar usuário</x-ui.button>
    </div>

    @if($errors->has('user'))
        <p class="mb-4 rounded-sm border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">{{ $errors->first('user') }}</p>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 text-sm text-mist">
        <p>{{ $users->total() }} {{ $users->total() === 1 ? 'usuário' : 'usuários' }}</p>
    </div>

    <div class="overflow-hidden rounded-sm border border-line bg-panel">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line text-xs uppercase tracking-wide text-mist">
                <tr>
                    <th class="px-4 py-3 font-medium sm:px-5">Usuário</th>
                    <th class="hidden px-4 py-3 font-medium md:table-cell">Papel</th>
                    <th class="hidden px-4 py-3 font-medium lg:table-cell">Estado</th>
                    <th class="hidden px-4 py-3 font-medium xl:table-cell">Último login</th>
                    <th class="px-4 py-3 font-medium text-right sm:px-5">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr @class([
                        'border-t border-line/70 transition hover:bg-ink/40',
                        'opacity-60' => ! $user->is_active,
                    ])>
                        <td class="px-4 py-4 sm:px-5">
                            <div class="flex items-center gap-3">
                                @if($user->avatarUrl())
                                    <img src="{{ $user->avatarUrl() }}" alt="" class="h-11 w-11 rounded-sm object-cover ring-1 ring-line">
                                @else
                                    <span class="inline-flex h-11 w-11 items-center justify-center rounded-sm border border-brand/30 bg-brand/10 text-xs font-semibold text-brand-bright">{{ $user->initials() }}</span>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-snow">{{ $user->name }}</p>
                                    <p class="truncate text-xs text-mist">{{ '@'.$user->username }} · {{ $user->email }}</p>
                                    <p class="mt-1 text-xs text-mist md:hidden">
                                        {{ $user->is_admin ? 'Admin' : 'Sem acesso admin' }}
                                        · {{ $user->is_active ? 'Ativo' : 'Desativado' }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="hidden px-4 py-4 md:table-cell">
                            @if($user->is_admin)
                                <span class="inline-flex rounded-sm bg-brand/15 px-2 py-1 text-xs font-semibold text-brand-bright">Admin</span>
                            @else
                                <span class="inline-flex rounded-sm border border-line px-2 py-1 text-xs text-mist">Sem acesso</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-4 lg:table-cell">
                            @if($user->is_active)
                                <span class="inline-flex rounded-sm border border-line px-2 py-1 text-xs text-snow">Ativo</span>
                            @else
                                <span class="inline-flex rounded-sm border border-red-500/30 bg-red-500/10 px-2 py-1 text-xs text-red-300">Desativado</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-4 text-mist xl:table-cell">
                            {{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-4 text-right sm:px-5">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <a href="{{ route('admin.users.edit', $user) }}" class="rounded-sm border border-line px-3 py-1.5 text-xs font-semibold text-snow transition hover:border-brand-bright/50">Editar</a>
                                @if(! $user->is(auth()->user()))
                                    <form method="POST" action="{{ route('admin.users.toggle-active', $user) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="rounded-sm border border-line px-3 py-1.5 text-xs font-semibold text-mist transition hover:border-brand-bright/50 hover:text-snow">
                                            {{ $user->is_active ? 'Desativar' : 'Reativar' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" data-confirm="Excluir este usuário permanentemente?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-sm border border-red-500/30 px-3 py-1.5 text-xs font-semibold text-red-300 transition hover:bg-red-500/10">Excluir</button>
                                    </form>
                                @else
                                    <span class="rounded-sm border border-line px-3 py-1.5 text-xs text-mist">Você</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-12 text-center text-mist">Nenhum usuário cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>

    <div class="mt-10">
        <x-admin.login-activity-log
            :activities="$loginActivities"
            title="Logs de acesso do sistema"
            description="Tentativas de login de todos os usuários do painel (sucesso e falha)."
            :show-user="true"
        />
    </div>

    <div class="mt-10">
        <x-admin.audit-log
            :logs="$auditLogs"
            title="Auditoria do sistema"
            description="Ações sensíveis: anexos, CRM, utilizadores e autenticação. Também gravadas em storage/logs/audit.log."
        />
    </div>
@endsection
