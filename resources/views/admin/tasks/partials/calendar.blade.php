<section class="task-calendar" aria-label="Calendário de atividades">
    <div class="task-calendar__toolbar">
        <div class="task-calendar__nav">
            <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => $prevMonth]) }}" class="task-calendar__nav-btn" aria-label="Mês anterior">‹</a>
            <h2 class="task-calendar__month">{{ \Illuminate\Support\Str::ucfirst($monthLabel) }}</h2>
            <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => $nextMonth]) }}" class="task-calendar__nav-btn" aria-label="Próximo mês">›</a>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.tasks.index', ['view' => 'calendar', 'month' => now()->format('Y-m')]) }}" class="task-calendar__today">Hoje</a>
            <button type="button" class="task-calendar__today" data-task-day="{{ now()->format('Y-m-d') }}" data-task-create>+ Compromisso</button>
        </div>
    </div>

    <div class="task-calendar__weekdays" aria-hidden="true">
        @foreach(['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $weekday)
            <span>{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="task-calendar__grid">
        @foreach($calendarDays as $day)
            <div
                class="task-calendar__cell {{ $day['in_month'] ? '' : 'is-muted' }} {{ $day['is_today'] ? 'is-today' : '' }}"
                role="button"
                tabindex="0"
                data-task-day="{{ $day['date'] }}"
                data-task-create
                title="Criar compromisso em {{ \Carbon\Carbon::parse($day['date'])->translatedFormat('d M Y') }}"
            >
                <div class="task-calendar__daynum">
                    <span>{{ $day['day'] }}</span>
                    <span class="task-calendar__add" aria-hidden="true">+</span>
                </div>
                <div class="task-calendar__events">
                    @foreach($day['tasks']->take(4) as $task)
                        @include('admin.tasks.partials.calendar-chip', [
                            'task' => $task,
                            'view' => 'agenda',
                            'month' => $month,
                        ])
                    @endforeach
                    @if($day['tasks']->count() > 4)
                        <p class="task-calendar__more">+{{ $day['tasks']->count() - 4 }} mais</p>
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
