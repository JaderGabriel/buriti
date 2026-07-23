<section class="task-agenda" aria-label="Agenda de atividades">
    <div class="task-agenda__toolbar">
        <div class="task-calendar__nav">
            <a href="{{ route('admin.tasks.index', ['view' => 'agenda', 'month' => $prevMonth]) }}" class="task-calendar__nav-btn" aria-label="Mês anterior">‹</a>
            <h2 class="task-calendar__month">{{ \Illuminate\Support\Str::ucfirst($monthLabel) }}</h2>
            <a href="{{ route('admin.tasks.index', ['view' => 'agenda', 'month' => $nextMonth]) }}" class="task-calendar__nav-btn" aria-label="Próximo mês">›</a>
        </div>
        <button
            type="button"
            class="task-calendar__today"
            data-task-day="{{ now()->format('Y-m-d') }}"
            data-task-create
        >
            Novo compromisso
        </button>
    </div>

    @if(!empty($googleEventsError))
        <div class="mb-4 rounded-xl border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
            {{ $googleEventsError }}
        </div>
    @elseif(!empty($googleApiReady) && ($googleEventsCount ?? 0) > 0)
        <p class="mb-4 text-xs text-mist">
            A mostrar também <strong class="text-snow">{{ $stats['google_month'] ?? $googleEventsCount }}</strong> evento(s) da Agenda Google (só leitura).
        </p>
    @elseif(empty($googleApiReady))
        <p class="mb-4 text-xs text-mist">
            Eventos criados só no Google aparecem aqui depois de ligar a API OAuth em
            <a href="{{ route('admin.settings.edit') }}#google-integration" class="text-brand-bright hover:underline">Configurações</a>.
        </p>
    @endif

    @php
        $agendaGoogleGroups = $agendaGoogleGroups ?? collect();
        $monthTaskGroups = $agendaGroups->filter(
            fn ($tasks, $date) => \Carbon\Carbon::parse($date)->format('Y-m') === $month
        );
        $monthGoogleGroups = $agendaGoogleGroups->filter(
            fn ($events, $date) => $date !== '' && \Carbon\Carbon::parse($date)->format('Y-m') === $month
        );
        $allDates = $monthTaskGroups->keys()
            ->merge($monthGoogleGroups->keys())
            ->unique()
            ->sort()
            ->values();
    @endphp

    @forelse($allDates as $date)
        @php
            $carbon = \Carbon\Carbon::parse($date);
            $dayTasks = $monthTaskGroups->get($date, collect());
            $dayGoogle = $monthGoogleGroups->get($date, collect());
        @endphp
        <div class="task-agenda__day {{ $carbon->isToday() ? 'is-today' : '' }}" id="day-{{ $date }}">
            <button
                type="button"
                class="task-agenda__date"
                data-task-day="{{ $date }}"
                data-task-create
                title="Criar atividade neste dia"
            >
                <span class="task-agenda__dow">{{ $carbon->translatedFormat('D') }}</span>
                <span class="task-agenda__dom">{{ $carbon->format('d') }}</span>
                <span class="task-agenda__moy">{{ $carbon->translatedFormat('M Y') }}</span>
            </button>
            <div class="task-agenda__items">
                @foreach($dayTasks as $task)
                    <div id="task-{{ $task->id }}">
                        @include('admin.tasks.partials.task-item', [
                            'task' => $task,
                            'view' => $view,
                            'month' => $month,
                            'statusLabels' => $statusLabels,
                            'priorityLabels' => $priorityLabels,
                            'projects' => $projects,
                            'contacts' => $contacts,
                            'googleEventColors' => $googleEventColors ?? null,
                        ])
                    </div>
                @endforeach
                @foreach($dayGoogle as $event)
                    @include('admin.tasks.partials.google-event-item', [
                        'event' => $event,
                        'googleCalendarUrl' => $googleCalendarUrl ?? null,
                    ])
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-sm border border-dashed border-line px-4 py-8 text-center text-sm text-mist">
            <p>Nenhuma atividade com data em {{ \Illuminate\Support\Str::ucfirst($monthLabel) }}.</p>
            <button
                type="button"
                class="mt-3 rounded-sm border border-line px-3 py-1.5 text-sm text-snow hover:border-brand-bright/50"
                data-task-day="{{ $month.'-01' }}"
                data-task-create
            >
                Criar primeiro compromisso
            </button>
        </div>
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
                        'googleEventColors' => $googleEventColors ?? null,
                    ])
                @endforeach
            </div>
        </div>
    @endif
</section>
