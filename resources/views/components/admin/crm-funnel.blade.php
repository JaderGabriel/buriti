@props([
    'counts' => [],
    'active' => null,
    'filterBase' => null,
])

@php
    $stages = \App\Enums\OpportunityStage::cases();
    $funnel = \App\Enums\OpportunityStage::funnelOrder();
    $totalOpen = collect($funnel)
        ->reject(fn ($v) => $v === 'won')
        ->sum(fn ($v) => (int) ($counts[$v] ?? 0));
@endphp

<section {{ $attributes->merge(['class' => 'crm-funnel', 'aria-label' => 'Funil comercial']) }}>
    <div class="crm-funnel__head">
        <div>
            <p class="crm-funnel__eyebrow">Funil CRM</p>
            <h2 class="crm-funnel__title">Lead → Contrato</h2>
        </div>
        <p class="crm-funnel__meta">{{ $totalOpen }} em aberto · {{ (int) ($counts['won'] ?? 0) }} contratos</p>
    </div>

    <ol class="crm-funnel__track">
        @foreach($stages as $stage)
            @php
                $count = (int) ($counts[$stage->value] ?? 0);
                $isActive = $active === $stage->value;
                $href = $filterBase
                    ? $filterBase.(str_contains($filterBase, '?') ? '&' : '?').'stage='.$stage->value
                    : null;
            @endphp
            <li @class(['crm-funnel__step', 'crm-funnel__step--'.$stage->tone(), 'is-active' => $isActive, 'is-empty' => $count === 0 && ! $isActive])>
                @if($href)
                    <a href="{{ $href }}" class="crm-funnel__link" title="{{ $stage->description() }}">
                @else
                    <div class="crm-funnel__link" title="{{ $stage->description() }}">
                @endif
                    <span class="crm-funnel__icon">
                        <x-ui.icon :name="$stage->icon()" class="h-4 w-4" />
                    </span>
                    <span class="crm-funnel__label">{{ $stage->label() }}</span>
                    <span class="crm-funnel__count">{{ $count }}</span>
                @if($href)
                    </a>
                @else
                    </div>
                @endif
                @unless($loop->last)
                    <span class="crm-funnel__arrow" aria-hidden="true">→</span>
                @endunless
            </li>
        @endforeach
    </ol>
</section>
