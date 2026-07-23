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
                    @php
                        $dayTasks = $day['tasks'];
                        $dayGoogle = $day['google_events'] ?? collect();
                        $visibleTasks = $dayTasks->take(3);
                        $remainingSlots = max(0, 4 - $visibleTasks->count());
                        $visibleGoogle = $dayGoogle->take($remainingSlots);
                        $hiddenCount = max(0, $dayTasks->count() - $visibleTasks->count())
                            + max(0, $dayGoogle->count() - $visibleGoogle->count());
                    @endphp
                    @foreach($visibleTasks as $task)
                        @include('admin.tasks.partials.calendar-chip', [
                            'task' => $task,
                            'view' => 'agenda',
                            'month' => $month,
                        ])
                    @endforeach
                    @foreach($visibleGoogle as $event)
                        @include('admin.tasks.partials.google-event-chip', [
                            'event' => $event,
                            'googleCalendarUrl' => $googleCalendarUrl ?? null,
                        ])
                    @endforeach
                    @if($hiddenCount > 0)
                        <p class="task-calendar__more">+{{ $hiddenCount }} mais</p>
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
