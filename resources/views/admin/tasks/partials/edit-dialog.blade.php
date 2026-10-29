<div
    id="task-edit-dialog"
    class="task-create-dialog"
    data-edit-base="{{ url('/admin/tarefas') }}"
    hidden
    role="dialog"
    aria-modal="true"
    aria-labelledby="task-edit-title"
    aria-hidden="true"
>
    <button type="button" class="task-create-dialog__backdrop" data-task-edit-close aria-label="Fechar"></button>
    <div class="task-create-dialog__panel task-create-dialog__panel--edit">
        <div data-task-edit-body>
            <p class="text-sm text-mist">Carregando tarefa…</p>
        </div>
    </div>
</div>
