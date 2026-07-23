@props(['message' => null, 'type' => null])

@php
    $flash = null;
    $flashType = $type;

    if ($message !== null) {
        $flash = $message;
        $flashType = $type ?? 'success';
    } elseif (session()->has('error')) {
        $flash = session('error');
        $flashType = 'error';
    } elseif (session()->has('warning')) {
        $flash = session('warning');
        $flashType = 'warning';
    } elseif (session()->has('success')) {
        $flash = session('success');
        $flashType = 'success';
    } elseif (session()->has('contact_success')) {
        $flash = session('contact_success');
        $flashType = 'success';
    }

    $classes = match ($flashType) {
        'error' => 'border-red-500/40 bg-red-500/10 text-red-300',
        'warning' => 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        default => 'border-brand/40 bg-brand/10 text-brand-bright',
    };
@endphp

@if($flash)
    <div {{ $attributes->merge(['class' => "mb-5 rounded-xl border px-4 py-3 text-sm $classes"]) }} role="status">
        {{ $flash }}
    </div>
@endif
