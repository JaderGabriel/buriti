<section class="task-agenda" aria-label="Agenda de atividades">
    <div class="task-agenda__toolbar">
        <div class="task-calendar__nav">
            <a href="{{ route('admin.tasks.index', ['view' => 'agenda', 'month' => $prevMonth]) }}" class="task-calendar__nav-btn" aria-label="Mês anterior">‹</a>
            <h2 class="task-calendar__month">{{ \Illuminate\Support\Str::ucfirst($monthLabel) }}</h2>
            <a href="{{ route('admin.tasks.index', ['view' => 'agenda', 'month' => $nextMonth]) }}" class="task-calendar__nav-btn" aria-label="Próximo mês">›</a>
        </div>
    </div>

    @php
        $monthGroups = $agendaGroups->filter(
            fn ($tasks, $date) => \Carbon\Carbon::parse($date)->format('Y-m') === $month
        );
    @endphp

    @forelse($monthGroups as $date => $dayTasks)
        @php $carbon = \Carbon\Carbon::parse($date); @endphp
        <div class="task-agenda__day {{ $carbon->isToday() ? 'is-today' : '' }}">
            <div class="task-agenda__date">
                <span class="task-agenda__dow">{{ $carbon->translatedFormat('D') }}</span>
                <span class="task-agenda__dom">{{ $carbon->format('d') }}</span>
                <span class="task-agenda__moy">{{ $carbon->translatedFormat('M Y') }}</span>
            </div>
            <div class="task-agenda__items">
                @foreach($dayTasks as $task)
                    @include('admin.tasks.partials.task-item', [
                        'task' => $task,
                        'view' => $view,
                        'month' => $month,
                        'statusLabels' => $statusLabels,
                        'priorityLabels' => $priorityLabels,
                        'projects' => $projects,
                        'contacts' => $contacts,
                    ])
                @endforeach
            </div>
        </div>
    @empty
        <p class="rounded-sm border border-dashed border-line px-4 py-8 text-sm text-mist">
            Nenhuma atividade com data em {{ \Illuminate\Support\Str::ucfirst($monthLabel) }}.
        </p>
    @endforelse

    @if($undatedTasks->isNotEmpty())
        <div class="task-agenda__day">
            <div class="task-agenda__date">
                <span class="task-agenda__dow">—</span>
                <span class="task-agenda__dom">∞</span>
                <span class="task-agenda__moy">Sem data</span>
            </div>
            <div class="task-agenda__items">
                @foreach($undatedTasks as $task)
                    @include('admin.tasks.partials.task-item', [
                        'task' => $task,
                        'view' => $view,
                        'month' => $month,
                        'statusLabels' => $statusLabels,
                        'priorityLabels' => $priorityLabels,
                        'projects' => $projects,
                        'contacts' => $contacts,
                    ])
                @endforeach
            </div>
        </div>
    @endif
</section>
