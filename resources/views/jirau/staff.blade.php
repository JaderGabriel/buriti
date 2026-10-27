@extends('layouts.jirau')

@section('content')
<div class="jirau-workspace">
    <div class="jirau-workspace__header">
        <div>
            <p class="pm-workspace__eyebrow">Jirau</p>
            <h1 class="pm-workspace__title">Equipe de TI</h1>
            <p class="pm-workspace__lead">
                O admin do sistema entra como Membro Admin. Dev, tester, PO e afins recebem cartões
                nos quadros — não são admin do painel e entram no Jirau com usuário e senha, sem 2FA.
            </p>
        </div>
        <div class="jirau-workspace__actions">
            <a href="{{ route('jirau.overview') }}" class="pm-btn pm-btn--ghost">Quadro geral</a>
        </div>
    </div>

    <div class="jirau-members__forms">
        <section class="jirau-members">
            <h2>Pessoas</h2>
            <ul class="jirau-members__list">
                @forelse($staff as $person)
                    <li>
                        <span>
                            <strong>{{ $person->name }}</strong>
                            <small>
                                {{ $person->jirauTeamLabel() }}
                                · {{ '@'.$person->username }}
                                · {{ $person->is_active ? 'ativa' : 'inativa' }}
                            </small>
                        </span>
                    </li>
                @empty
                    <li class="jirau-members__empty">Ainda sem equipe de TI. Crie a primeira pessoa ao lado.</li>
                @endforelse
            </ul>
        </section>

        <form method="POST" action="{{ route('jirau.staff.store') }}" class="jirau-compose">
            @csrf
            <h2>Novo da equipe</h2>
            <x-ui.input name="name" label="Nome" required />
            <x-ui.input name="username" label="Usuário" required placeholder="ana.dev" />
            <x-ui.input type="email" name="email" label="E-mail" required />
            <label class="block text-sm">
                <span class="text-mist">Papel</span>
                <select name="jirau_role" class="mt-1.5 w-full rounded-sm border border-line bg-ink px-3 py-2.5" required>
                    @foreach($roles as $value => $label)
                        <option value="{{ $value }}" @selected(old('jirau_role') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <x-ui.input type="password" name="password" label="Senha" required />
            <x-ui.input type="password" name="password_confirmation" label="Confirmar senha" required />
            <button type="submit" class="pm-btn pm-btn--primary">Criar pessoa</button>
        </form>
    </div>
</div>
@endsection
