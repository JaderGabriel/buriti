@props([
    'logs',
    'title' => 'Auditoria',
    'description' => 'Registo de ações sensíveis do painel (Laravel audit log).',
    'showUser' => true,
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
            {{ $logs->count() }} evento{{ $logs->count() === 1 ? '' : 's' }}
        </span>
    </div>

    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[36rem] text-left text-sm">
            <thead class="border-b border-line text-xs uppercase tracking-wide text-mist">
                <tr>
                    <th class="pb-2 pr-3 font-medium">Ação</th>
                    @if($showUser)
                        <th class="pb-2 pr-3 font-medium">Quem</th>
                    @endif
                    <th class="pb-2 pr-3 font-medium">Detalhe</th>
                    <th class="hidden pb-2 pr-3 font-medium md:table-cell">IP</th>
                    <th class="pb-2 font-medium text-right">Quando</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-t border-line/70">
                        <td class="py-2.5 pr-3">
                            <span class="font-medium text-snow">{{ $log->actionLabel() }}</span>
                            <p class="text-[10px] text-mist">{{ $log->action }}</p>
                        </td>
                        @if($showUser)
                            <td class="py-2.5 pr-3 text-mist">
                                {{ $log->user?->name ?? 'Sistema' }}
                            </td>
                        @endif
                        <td class="py-2.5 pr-3 text-mist">
                            <span class="line-clamp-2" title="{{ $log->summary() }}">{{ $log->summary() }}</span>
                        </td>
                        <td class="hidden py-2.5 pr-3 text-xs text-mist md:table-cell">{{ $log->ip_address ?? '—' }}</td>
                        <td class="py-2.5 text-right text-xs text-mist whitespace-nowrap">
                            {{ $log->created_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $showUser ? 5 : 4 }}" class="py-8 text-center text-mist">Sem eventos de auditoria.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($logs, 'links'))
        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</section>
