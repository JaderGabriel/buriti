<div class="task-board">
    @foreach ($statusLabels as $status => $label)
        <section class="task-board__column">
            <header class="task-board__header">
                <h2>{{ $label }}</h2>
                <span>{{ $columns[$status]->count() }}</span>
            </header>
            <div class="task-board__list">
                @forelse($columns[$status] as $task)
                    @include('admin.tasks.partials.task-item', [
                        'task' => $task,
                        'view' => $view,
                        'month' => $month,
                        'statusLabels' => $statusLabels,
                        'priorityLabels' => $priorityLabels,
                        'projects' => $projects,
                        'contacts' => $contacts,
                    ])
                @empty
                    <p class="text-sm text-mist">Vazio</p>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
