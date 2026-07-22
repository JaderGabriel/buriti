@extends('layouts.app')

@section('title', 'Login — BURI-TI')

@section('body')
<div class="flex min-h-screen items-center justify-center px-5">
    <div class="w-full max-w-md rounded-3xl border border-line bg-panel p-8">
        <div class="mb-8 text-center">
            <img src="{{ asset('images/logo-buriti.png') }}" alt="BURI-TI" class="mx-auto h-16 w-16 object-contain">
            <h1 class="mt-4 font-display text-2xl font-bold">Acesso admin</h1>
            <p class="mt-1 text-sm text-mist">Gerencie mensagens, projetos e tarefas</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <label class="block text-sm">
                <span class="text-mist">E-mail</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus
                       class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 outline-none ring-brand-bright focus:ring-1">
                @error('email') <span class="mt-1 block text-xs text-red-400">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">
                <span class="text-mist">Senha</span>
                <input type="password" name="password" required
                       class="mt-1.5 w-full rounded-xl border border-line bg-ink px-3 py-2.5 outline-none ring-brand-bright focus:ring-1">
            </label>
            <label class="flex items-center gap-2 text-sm text-mist">
                <input type="checkbox" name="remember" value="1" class="rounded border-line">
                Lembrar-me
            </label>
            <button class="w-full rounded-full bg-brand py-3 text-sm font-semibold text-white hover:bg-brand-bright">Entrar</button>
        </form>
    </div>
</div>
@endsection
