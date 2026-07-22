@extends('layouts.admin')

@section('content')
    <div class="mb-8">
        <h1 class="font-display text-3xl font-bold">Mensagens</h1>
        <p class="mt-1 text-mist">Publicações enviadas pelo formulário do site</p>
    </div>

    <div class="overflow-hidden rounded-2xl border border-line">
        <table class="w-full text-left text-sm">
            <thead class="bg-panel text-mist">
                <tr>
                    <th class="px-4 py-3 font-medium">De</th>
                    <th class="hidden px-4 py-3 font-medium lg:table-cell">Telefone</th>
                    <th class="hidden px-4 py-3 font-medium md:table-cell">Assunto</th>
                    <th class="px-4 py-3 font-medium">Quando</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($messages as $message)
                    <tr class="border-t border-line hover:bg-panel/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.messages.show', $message) }}" class="font-medium text-snow hover:text-brand-bright">
                                {{ $message->name }}
                            </a>
                            <p class="text-xs text-mist">{{ $message->email }}</p>
                        </td>
                        <td class="hidden px-4 py-3 text-mist lg:table-cell">
                            @if($message->phone)
                                <a href="tel:{{ preg_replace('/\s+/', '', $message->phone) }}" class="hover:text-brand-bright">{{ $message->phone }}</a>
                                @if($message->preferred_channel)
                                    <p class="text-xs uppercase tracking-wide text-mist/80">{{ $message->preferred_channel }}</p>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="hidden px-4 py-3 text-mist md:table-cell">{{ $message->subject }}</td>
                        <td class="px-4 py-3 text-mist">{{ $message->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if($message->isUnread())
                                <span class="rounded-full bg-brand/20 px-2 py-1 text-xs text-brand-bright">Nova</span>
                            @else
                                <span class="text-xs text-mist">Lida</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-mist">Nenhuma mensagem recebida.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $messages->links() }}</div>
@endsection
