@props([
    'stage' => null,
    'status' => null,
    'compact' => false,
])

@php
    if ($stage instanceof \App\Enums\OpportunityStage) {
        $label = $stage->label();
        $tone = $stage->tone();
        $icon = $stage->icon();
        $title = $stage->description();
    } elseif ($status instanceof \App\Enums\ContactStatus) {
        $label = $status->label();
        $tone = $status->tone();
        $icon = $status->icon();
        $title = $status->description();
    } elseif ($status instanceof \App\Enums\CompanyStatus) {
        $label = $status->label();
        $tone = $status->tone();
        $icon = $status->icon();
        $title = $status->description();
    } else {
        $label = '—';
        $tone = 'lead';
        $icon = 'lead';
        $title = '';
    }
@endphp

<span
    {{ $attributes->class(['crm-badge', 'crm-badge--'.$tone, 'crm-badge--compact' => $compact]) }}
    title="{{ $title }}"
>
    <x-ui.icon :name="$icon" class="h-3.5 w-3.5" />
    <span>{{ $label }}</span>
</span>
