@extends('layouts.admin')

@section('content')
    <div class="mb-8 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div>
            <h1 class="font-display text-2xl font-bold sm:text-3xl">Oportunidades</h1>
            <p class="mt-1 text-mist">Possibilidades comerciais e pipeline</p>
        </div>
        <x-ui.button :href="route('admin.opportunities.create')">Nova oportunidade</x-ui.button>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap gap-3">
        <select name="stage" class="rounded-sm border border-line bg-ink/40 px-3 py-2 text-sm text-snow">
            <option value="">Todos os estágios</option>
            @foreach($stages as $value => $label)
                <option value="{{ $value }}" @selected(request('stage') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="rounded-sm border border-line px-4 py-2 text-sm hover:border-brand-bright/50">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-sm border border-line bg-panel">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-line text-xs uppercase tracking-wide text-mist">
                <tr>
                    <th class="px-4 py-3 font-medium">Título</th>
                    <th class="px-4 py-3 font-medium">Contato</th>
                    <th class="px-4 py-3 font-medium">Projeto</th>
                    <th class="px-4 py-3 font-medium">Estágio</th>
                    <th class="px-4 py-3 font-medium">Valor</th>
                    <th class="px-4 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($opportunities as $opportunity)
                    <tr class="border-b border-line/70 hover:bg-ink/30">
                        <td class="px-4 py-3 font-medium">{{ $opportunity->title }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.contacts.show', $opportunity->contact) }}" class="text-brand-bright hover:underline">
                                {{ $opportunity->contact->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-mist">{{ $opportunity->project?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $opportunity->stage->label() }}</td>
                        <td class="px-4 py-3 text-mist">
                            @if($opportunity->value)
                                R$ {{ number_format((float) $opportunity->value, 2, ',', '.') }}
                            @else — @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.opportunities.edit', $opportunity) }}" class="text-brand-bright hover:underline">Editar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-mist">Nenhuma oportunidade.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $opportunities->links() }}</div>
@endsection
