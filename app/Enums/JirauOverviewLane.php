<?php

namespace App\Enums;

enum JirauOverviewLane: string
{
    case Demand = 'demand';
    case Backlog = 'backlog';
    case Doing = 'doing';
    case Testing = 'testing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Demand => 'Demandas',
            self::Backlog => 'Backlog',
            self::Doing => 'Em andamento',
            self::Testing => 'Em teste',
            self::Done => 'Concluídos',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Demand => '#c74634',
            self::Backlog => '#5b6b82',
            self::Doing => '#2f6bff',
            self::Testing => '#7c3aed',
            self::Done => '#0f9d58',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Demand => 'Inbox e pedidos ainda sem triagem.',
            self::Backlog => 'Pronto para puxar — planejamento, proposta, a fazer.',
            self::Doing => 'Trabalho em curso nos quadros de origem.',
            self::Testing => 'Revisão, homologação, avaliação ou espera.',
            self::Done => 'Entregue nos Jiraus ativos (ou no filtro escolhido).',
        };
    }
}
