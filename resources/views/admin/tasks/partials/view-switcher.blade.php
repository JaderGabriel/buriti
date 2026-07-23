@php
    $views = [
        'calendar' => ['label' => 'Calendário', 'hint' => 'Mês'],
        'agenda' => ['label' => 'Agenda', 'hint' => 'Linha do tempo'],
        'board' => ['label' => 'Quadro', 'hint' => 'Kanban'],
        'list' => ['label' => 'Lista', 'hint' => 'Tabela'],
    ];
@endphp

<nav class="task-view-switch" aria-label="Tipo de visualização">
    @foreach($views as $key => $meta)
        <a
            href="{{ route('admin.tasks.index', array_filter(['view' => $key, 'month' => $month ?? null])) }}"
            class="task-view-switch__btn {{ $view === $key ? 'is-active' : '' }}"
            @if($view === $key) aria-current="page" @endif
        >
            <span class="task-view-switch__label">{{ $meta['label'] }}</span>
            <span class="task-view-switch__hint">{{ $meta['hint'] }}</span>
        </a>
    @endforeach
</nav>
