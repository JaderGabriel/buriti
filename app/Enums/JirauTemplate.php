<?php

namespace App\Enums;

enum JirauTemplate: string
{
    case Development = 'development';
    case Consulting = 'consulting';
    case Training = 'training';
    case Activities = 'activities';

    public function label(): string
    {
        return match ($this) {
            self::Development => 'Desenvolvimento',
            self::Consulting => 'Consultoria',
            self::Training => 'Treinamento',
            self::Activities => 'Atividades',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Development => 'Kanban ágil: demandas, backlog, WIP, revisão e entrega.',
            self::Consulting => 'Diagnóstico → proposta → execução → homologação, no ritmo da consultoria.',
            self::Training => 'Da turma ao material e à avaliação, para ciclos de capacitação.',
            self::Activities => 'Quadro operacional simples para tarefas, reuniões e follow-ups.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Development => '#2f6bff',
            self::Consulting => '#1a5f9e',
            self::Training => '#7c3aed',
            self::Activities => '#0f9d58',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Development => 'code',
            self::Consulting => 'consulting',
            self::Training => 'train',
            self::Activities => 'task',
        };
    }

    public function lead(): string
    {
        return match ($this) {
            self::Development => 'Demandas entram na triagem; o admin estima e posiciona até Feito.',
            self::Consulting => 'Pedidos entram no inbox; o fluxo conduz diagnóstico, proposta e entrega.',
            self::Training => 'Pedidos de turma ou material entram no inbox e seguem até a conclusão.',
            self::Activities => 'Atividades entram no inbox; o admin prioriza, executa e fecha.',
        };
    }

    /**
     * @return list<array{name: string, slug: string, is_inbox: bool, is_done: bool, wip_limit: ?int, color: string}>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Development => [
                ['name' => 'Demandas', 'slug' => 'inbox', 'is_inbox' => true, 'is_done' => false, 'wip_limit' => null, 'color' => '#c74634'],
                ['name' => 'Backlog', 'slug' => 'backlog', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#5b6b82'],
                ['name' => 'Pronto', 'slug' => 'ready', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#2f7ec4'],
                ['name' => 'Em curso', 'slug' => 'doing', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 3, 'color' => '#2f6bff'],
                ['name' => 'Revisão', 'slug' => 'review', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 3, 'color' => '#7c3aed'],
                ['name' => 'Feito', 'slug' => 'done', 'is_inbox' => false, 'is_done' => true, 'wip_limit' => null, 'color' => '#0f9d58'],
            ],
            self::Consulting => [
                ['name' => 'Inbox', 'slug' => 'inbox', 'is_inbox' => true, 'is_done' => false, 'wip_limit' => null, 'color' => '#c74634'],
                ['name' => 'Diagnóstico', 'slug' => 'discovery', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#1a5f9e'],
                ['name' => 'Proposta', 'slug' => 'proposal', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 2, 'color' => '#2f7ec4'],
                ['name' => 'Execução', 'slug' => 'doing', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 3, 'color' => '#2f6bff'],
                ['name' => 'Homologação', 'slug' => 'review', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 2, 'color' => '#7c3aed'],
                ['name' => 'Encerrado', 'slug' => 'done', 'is_inbox' => false, 'is_done' => true, 'wip_limit' => null, 'color' => '#0f9d58'],
            ],
            self::Training => [
                ['name' => 'Pedidos', 'slug' => 'inbox', 'is_inbox' => true, 'is_done' => false, 'wip_limit' => null, 'color' => '#c74634'],
                ['name' => 'Planejamento', 'slug' => 'planning', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#5b6b82'],
                ['name' => 'Material', 'slug' => 'material', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 3, 'color' => '#2f7ec4'],
                ['name' => 'Turma', 'slug' => 'class', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 2, 'color' => '#2f6bff'],
                ['name' => 'Em aula', 'slug' => 'doing', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 2, 'color' => '#7c3aed'],
                ['name' => 'Avaliação', 'slug' => 'review', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 2, 'color' => '#c4a035'],
                ['name' => 'Concluído', 'slug' => 'done', 'is_inbox' => false, 'is_done' => true, 'wip_limit' => null, 'color' => '#0f9d58'],
            ],
            self::Activities => [
                ['name' => 'Inbox', 'slug' => 'inbox', 'is_inbox' => true, 'is_done' => false, 'wip_limit' => null, 'color' => '#c74634'],
                ['name' => 'A fazer', 'slug' => 'todo', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#5b6b82'],
                ['name' => 'Em andamento', 'slug' => 'doing', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => 4, 'color' => '#2f6bff'],
                ['name' => 'Aguardando', 'slug' => 'waiting', 'is_inbox' => false, 'is_done' => false, 'wip_limit' => null, 'color' => '#c4a035'],
                ['name' => 'Feito', 'slug' => 'done', 'is_inbox' => false, 'is_done' => true, 'wip_limit' => null, 'color' => '#0f9d58'],
            ],
        };
    }

    /**
     * @return list<array{name: string, color: string}>
     */
    public function labels(): array
    {
        return match ($this) {
            self::Development => [
                ['name' => 'Bug', 'color' => '#c74634'],
                ['name' => 'Urgente', 'color' => '#e24b35'],
                ['name' => 'Bloqueado', 'color' => '#c4a035'],
                ['name' => 'Frontend', 'color' => '#2f7ec4'],
                ['name' => 'Backend', 'color' => '#1a5f9e'],
                ['name' => 'Design', 'color' => '#7c3aed'],
            ],
            self::Consulting => [
                ['name' => 'Comercial', 'color' => '#2f7ec4'],
                ['name' => 'Técnico', 'color' => '#1a5f9e'],
                ['name' => 'Contrato', 'color' => '#0f9d58'],
                ['name' => 'Urgente', 'color' => '#e24b35'],
                ['name' => 'Bloqueado', 'color' => '#c4a035'],
                ['name' => 'Follow-up', 'color' => '#7c3aed'],
            ],
            self::Training => [
                ['name' => 'Presencial', 'color' => '#2f6bff'],
                ['name' => 'Online', 'color' => '#2f7ec4'],
                ['name' => 'Turma', 'color' => '#7c3aed'],
                ['name' => 'Individual', 'color' => '#1a5f9e'],
                ['name' => 'Urgente', 'color' => '#e24b35'],
                ['name' => 'Material', 'color' => '#c4a035'],
            ],
            self::Activities => [
                ['name' => 'Reunião', 'color' => '#2f6bff'],
                ['name' => 'Follow-up', 'color' => '#7c3aed'],
                ['name' => 'Interno', 'color' => '#5b6b82'],
                ['name' => 'Cliente', 'color' => '#2f7ec4'],
                ['name' => 'Urgente', 'color' => '#e24b35'],
                ['name' => 'Bloqueado', 'color' => '#c4a035'],
            ],
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
