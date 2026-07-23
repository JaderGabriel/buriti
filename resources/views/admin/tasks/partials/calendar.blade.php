<section class="task-calendar" aria-label="Calendário de atividades">
    <div class="task-calendar__toolbar">
        <div class="task-calendar__nav">
            <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => $prevMonth]) }}" class="task-calendar__nav-btn" aria-label="Mês anterior">‹</a>
            <h2 class="task-calendar__month">{{ \Illuminate\Support\Str::ucfirst($monthLabel) }}</h2>
            <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => $nextMonth]) }}" class="task-calendar__nav-btn" aria-label="Próximo mês">›</a>
        </div>
        <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => now()->format('Y-m')]) }}" class="task-calendar__today">Hoje</a>
    </div>

    <div class="task-calendar__weekdays" aria-hidden="true">
        @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <span>{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="task-calendar__grid">
        @foreach($calendarDays as $day)
            <div class="task-calendar__cell {{ $day['in_month'] ? '' : 'is-muted' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                <div class="task-calendar__daynum">{{ $day['day'] }}</div>
                <div class="task-calendar__events">
                    @foreach($day['tasks']->take(3) as $task)
                        <div class="task-calendar__event task-calendar__event--{{ $task->status->value }}">
                            @include('admin.tasks.partials.task-item', [
                                'task' => $task,
                                'compact' => true,
                                'view' => $view,
                                'month' => $month,
                                'statusLabels' => $statusLabels,
                                'priorityLabels' => $priorityLabels,
                                'projects' => $projects,
                                'contacts' => $contacts,
                            ])
                        </div>
                    @endforeach
                    @if($day['tasks']->count() > 3)
                        <p class="task-calendar__more">+{{ $day['tasks']->count() - 3 }} mais</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($undatedTasks->isNotEmpty())
        <aside class="task-calendar__undated">
            <h3 class="task-calendar__undated-title">Sem data agendada ({{ $undatedTasks->count() }})</h3>
            <div class="task-calendar__undated-list">
                @foreach($undatedTasks as $task)
                    @include('admin.tasks.partials.task-item', [
                        'task' => $task,
                        'compact' => false,
                        'view' => $view,
                        'month' => $month,
                        'statusLabels' => $statusLabels,
                        'priorityLabels' => $priorityLabels,
                        'projects' => $projects,
                        'contacts' => $contacts,
                    ])
                @endforeach
            </div>
        </aside>
    @endif
</section>
