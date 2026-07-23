@props([
    'activities',
    'title' => 'Histórico de login',
    'description' => null,
    'showUser' => false,
])

<section {{ $attributes->merge(['class' => 'rounded-sm border border-line bg-panel p-5 sm:p-6']) }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="font-display text-lg font-semibold">{{ $title }}</h2>
            @if($description)
                <p class="mt-1 text-xs text-mist">{{ $description }}</p>
            @endif
        </div>
        <span class="rounded-full bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-mist">
            {{ $activities->count() }} registro{{ $activities->count() === 1 ? '' : 's' }}
        </span>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[32rem] text-left text-sm">
            <thead class="border-b border-line text-xs uppercase tracking-wide text-mist">
                <tr>
                    <th class="pb-2 pr-3 font-medium">Resultado</th>
                    @if($showUser)
                        <th class="pb-2 pr-3 font-medium">Usuário / e-mail</th>
                    @endif
                    <th class="pb-2 pr-3 font-medium">IP</th>
                    <th class="hidden pb-2 pr-3 font-medium md:table-cell">Agente</th>
                    <th class="pb-2 font-medium text-right">Quando</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                    <tr class="border-t border-line/70">
                        <td class="py-2.5 pr-3">
                            <span @class([
                                'inline-flex rounded-sm px-2 py-0.5 text-xs font-semibold',
                                'bg-brand/15 text-brand-bright' => $activity->successful,
                                'bg-red-500/10 text-red-300' => ! $activity->successful,
                            ])>
                                {{ $activity->successful ? 'Sucesso' : 'Falha' }}
                            </span>
                        </td>
                        @if($showUser)
                            <td class="py-2.5 pr-3">
                                <p class="font-medium text-snow">
                                    {{ $activity->user?->name ?? '—' }}
                                </p>
                                <p class="text-xs text-mist">{{ $activity->email ?? $activity->user?->email ?? '—' }}</p>
                            </td>
                        @endif
                        <td class="py-2.5 pr-3 text-mist">{{ $activity->ip_address ?? '—' }}</td>
                        <td class="hidden py-2.5 pr-3 text-xs text-mist md:table-cell" title="{{ $activity->user_agent }}">
                            {{ $activity->browserSummary() }}
                        </td>
                        <td class="py-2.5 text-right text-xs text-mist whitespace-nowrap">
                            {{ $activity->created_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showUser ? 5 : 4 }}" class="py-8 text-center text-mist">Sem registros de login ainda.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($activities, 'links'))
        <div class="mt-4">{{ $activities->links() }}</div>
    @endif
</section>
