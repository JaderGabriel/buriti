<section class="task-list" aria-label="Lista de tarefas">
    <div class="task-list__table-wrap">
        <table class="task-list__table">
            <thead>
                <tr>
                    <th>Atividade</th>
                    <th>Quando</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Projeto</th>
                    <th>Contato</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $task)
                    <tr>
                        <td>
                            @include('admin.tasks.partials.task-item', [
                                'task' => $task,
                                'view' => $view,
                                'month' => $month,
                                'statusLabels' => $statusLabels,
                                'priorityLabels' => $priorityLabels,
                                'projects' => $projects,
                                'contacts' => $contacts,
                            ])
                        </td>
                        <td class="whitespace-nowrap text-sm text-mist">
                            {{ $task->due_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="text-sm">{{ $task->status->label() }}</td>
                        <td class="text-sm">{{ $task->priority->label() }}</td>
                        <td class="text-sm text-mist">{{ $task->project?->name ?? '—' }}</td>
                        <td class="text-sm text-mist">{{ $task->contact?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-sm text-mist">Nenhuma tarefa cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
