@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-2xl font-bold sm:text-3xl">Busca</h1>
        <p class="mt-1 text-mist">Contactos, empresas, oportunidades e actividades</p>
    </div>

    <form method="GET" action="{{ route('admin.search') }}" class="mb-6 max-w-xl">
        <label class="block text-sm text-mist">
            Termo
            <input type="search" name="q" value="{{ $q }}" class="mt-1.5 w-full rounded-sm border border-line bg-ink/40 px-3 py-2 text-snow" placeholder="Nome, e-mail, assunto…">
        </label>
        <button type="submit" class="pm-btn pm-btn--primary mt-3">Buscar</button>
    </form>

    @if(mb_strlen($q) < 2)
        <p class="text-sm text-mist">Escreva pelo menos 2 caracteres.</p>
    @elseif($hits === [])
        <p class="text-sm text-mist">Nenhum resultado para «{{ $q }}».</p>
    @else
        <ul class="space-y-2">
            @foreach($hits as $hit)
                <li>
                    <a href="{{ $hit['url'] }}" class="block rounded-sm border border-line bg-panel px-4 py-3 hover:border-brand/40">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-mist">{{ $hit['type'] }}</p>
                        <p class="font-medium text-snow">{{ $hit['title'] }}</p>
                        @if($hit['subtitle'] !== '')
                            <p class="text-sm text-mist">{{ $hit['subtitle'] }}</p>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
