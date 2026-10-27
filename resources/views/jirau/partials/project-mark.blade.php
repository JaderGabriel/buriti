@php
    $project = $project ?? null;
    $markClass = trim('jirau-project-mark '.($markClass ?? ''));
@endphp
@if($project)
    @if($project->logoUrl())
        <img
            src="{{ $project->logoUrl() }}"
            alt=""
            class="{{ $markClass }}"
            title="{{ $project->name }}"
            draggable="false"
        >
    @else
        <span class="{{ $markClass }} jirau-project-mark--fallback" title="{{ $project->name }}" aria-hidden="true">
            {{ strtoupper(mb_substr($project->name, 0, 1)) }}
        </span>
    @endif
@endif
