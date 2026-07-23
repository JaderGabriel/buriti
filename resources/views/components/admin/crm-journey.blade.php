@props([
    'current' => null,
])

@php
    $steps = [
        ['key' => 'message', 'label' => 'Mensagem', 'icon' => 'message', 'route' => 'admin.messages.index'],
        ['key' => 'contact', 'label' => 'Contato', 'icon' => 'contact', 'route' => 'admin.contacts.index'],
        ['key' => 'opportunity', 'label' => 'Oportunidade', 'icon' => 'opportunity', 'route' => 'admin.opportunities.index'],
        ['key' => 'project', 'label' => 'Projeto', 'icon' => 'project', 'route' => 'admin.projects.index'],
        ['key' => 'contract', 'label' => 'Contrato', 'icon' => 'contract', 'route' => 'admin.opportunities.index'],
    ];
@endphp

<nav {{ $attributes->merge(['class' => 'crm-journey', 'aria-label' => 'Jornada comercial']) }}>
    @foreach($steps as $step)
        @php $isCurrent = $current === $step['key']; @endphp
        <a
            href="{{ route($step['route'], $step['key'] === 'contract' ? ['stage' => 'won', 'view' => 'board'] : []) }}"
            @class(['crm-journey__step', 'is-current' => $isCurrent])
        >
            <span class="crm-journey__icon"><x-ui.icon :name="$step['icon']" class="h-4 w-4" /></span>
            <span>{{ $step['label'] }}</span>
        </a>
        @unless($loop->last)
            <span class="crm-journey__sep" aria-hidden="true">›</span>
        @endunless
    @endforeach
</nav>
