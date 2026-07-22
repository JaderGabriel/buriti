@extends('layouts.admin')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.messages.index') }}" class="text-sm text-mist hover:text-snow">← Mensagens</a>
            <h1 class="mt-2 font-display text-3xl font-bold">{{ $message->subject }}</h1>
        </div>
        <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" data-confirm="Remover esta mensagem?">
            @csrf
            @method('DELETE')
            <button class="rounded-full border border-red-500/40 px-4 py-2 text-sm text-red-300 hover:bg-red-500/10">Remover</button>
        </form>
    </div>

    <article class="rounded-2xl border border-line bg-panel p-6">
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-mist">Nome</dt>
                <dd class="mt-1 font-medium">{{ $message->name }}</dd>
            </div>
            <div>
                <dt class="text-mist">E-mail</dt>
                <dd class="mt-1"><a href="mailto:{{ $message->email }}" class="text-brand-bright hover:underline">{{ $message->email }}</a></dd>
            </div>
            @if($message->phone)
                <div>
                    <dt class="text-mist">Telefone</dt>
                    <dd class="mt-1">{{ $message->phone }}</dd>
                </div>
            @endif
            @if($message->company)
                <div>
                    <dt class="text-mist">Empresa</dt>
                    <dd class="mt-1">{{ $message->company }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-mist">Recebida em</dt>
                <dd class="mt-1">{{ $message->created_at->format('d/m/Y H:i') }}</dd>
            </div>
        </dl>
        <div class="mt-6 border-t border-line pt-6">
            <p class="text-sm text-mist">Mensagem</p>
            <p class="mt-2 whitespace-pre-wrap leading-relaxed">{{ $message->message }}</p>
        </div>
        <div class="mt-6 flex flex-wrap gap-3">
            <a href="mailto:{{ $message->email }}?subject=Re: {{ urlencode($message->subject) }}" class="rounded-full bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-bright">Responder por e-mail</a>
        </div>
    </article>
@endsection
