<section class="contact-agenda" aria-label="Agenda do contato">
    <div class="contact-agenda__toolbar">
        <div class="contact-agenda__nav">
            <a
                href="{{ route('admin.contacts.show', [$contact, 'month' => $prevMonth]) }}#agenda-contato"
                class="contact-agenda__nav-btn"
                aria-label="Mês anterior"
            >‹</a>
            <h3 class="contact-agenda__month">{{ $monthLabel }}</h3>
            <a
                href="{{ route('admin.contacts.show', [$contact, 'month' => $nextMonth]) }}#agenda-contato"
                class="contact-agenda__nav-btn"
                aria-label="Próximo mês"
            >›</a>
        </div>
        <a
            href="{{ route('admin.contacts.show', [$contact, 'month' => now()->format('Y-m')]) }}#agenda-contato"
            class="contact-agenda__today"
        >Hoje</a>
    </div>

    <div class="contact-agenda__weekdays" aria-hidden="true">
        @foreach(['D', 'S', 'T', 'Q', 'Q', 'S', 'S'] as $weekday)
            <span>{{ $weekday }}</span>
        @endforeach
    </div>

    <div class="contact-agenda__grid" id="agenda-contato">
        @foreach($calendarDays as $day)
            <div class="contact-agenda__cell {{ $day['in_month'] ? '' : 'is-muted' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                <span class="contact-agenda__daynum">{{ $day['day'] }}</span>
                <div class="contact-agenda__events">
                    @foreach($day['tasks']->take(3) as $task)
                        <a
                            href="{{ route('admin.tasks.index', ['view' => 'agenda', 'q' => $task->title]) }}"
                            class="contact-agenda__chip contact-agenda__chip--{{ $task->status->value }}"
                            title="{{ $task->title }} · {{ $task->status->label() }}{{ $task->due_at ? ' · '.$task->due_at->format('H:i') : '' }}"
                        >
                            @if($task->due_at)
                                <span class="contact-agenda__chip-time">{{ $task->due_at->format('H:i') }}</span>
                            @endif
                            <span class="contact-agenda__chip-title">{{ $task->title }}</span>
                        </a>
                    @endforeach
                    @if($day['tasks']->count() > 3)
                        <p class="contact-agenda__more">+{{ $day['tasks']->count() - 3 }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($undatedTasks->isNotEmpty())
        <div class="contact-agenda__undated">
            <p class="contact-agenda__undated-title">Sem data ({{ $undatedTasks->count() }})</p>
            <ul class="contact-agenda__undated-list">
                @foreach($undatedTasks as $task)
                    <li>
                        <span class="font-medium text-snow">{{ $task->title }}</span>
                        <span class="text-xs text-mist"> · {{ $task->status->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif($contact->tasks->isEmpty())
        <p class="contact-agenda__empty">Nenhuma tarefa ligada a este contato.</p>
    @endif
</section>
