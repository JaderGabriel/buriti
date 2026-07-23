<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
        'url',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login.success' => 'Login com sucesso',
            'auth.login.failed' => 'Login falhou',
            'auth.logout' => 'Logout',
            'auth.session.destroy' => 'Sessão encerrada',
            'auth.session.destroy_others' => 'Outras sessões encerradas',
            'auth.session.destroy_all' => 'Todas as sessões encerradas',
            'attachment.created' => 'Anexo adicionado',
            'attachment.trashed' => 'Anexo ocultado',
            'attachment.restored' => 'Anexo recuperado',
            'attachment.purged' => 'Anexo eliminado em definitivo',
            'contact.created' => 'Contato criado',
            'contact.updated' => 'Contato atualizado',
            'contact.deleted' => 'Contato removido',
            'opportunity.created' => 'Oportunidade criada',
            'opportunity.updated' => 'Oportunidade atualizada',
            'opportunity.deleted' => 'Oportunidade removida',
            'project.created' => 'Projeto criado',
            'project.updated' => 'Projeto atualizado',
            'project.deleted' => 'Projeto removido',
            'task.created' => 'Tarefa criada',
            'task.updated' => 'Tarefa atualizada',
            'task.deleted' => 'Tarefa removida',
            'user.created' => 'Usuário criado',
            'user.updated' => 'Usuário atualizado',
            'user.deleted' => 'Usuário removido',
            'user.deactivated' => 'Usuário desativado',
            'user.reactivated' => 'Usuário reativado',
            'message.deleted' => 'Mensagem removida',
            'message.read' => 'Mensagem lida',
            default => str_replace(['.', '_'], [' → ', ' '], $this->action),
        };
    }

    public function summary(): string
    {
        $props = $this->properties ?? [];

        return (string) ($props['summary']
            ?? $props['original_name']
            ?? $props['title']
            ?? $props['name']
            ?? $props['email']
            ?? '—');
    }
}
