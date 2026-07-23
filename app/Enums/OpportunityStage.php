<?php

namespace App\Enums;

enum OpportunityStage: string
{
    case Lead = 'lead';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Qualified => 'Qualificado',
            self::Proposal => 'Proposta',
            self::Negotiation => 'Negociação',
            self::Won => 'Contrato',
            self::Lost => 'Perdido',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Lead => 'Entrada comercial — interesse inicial',
            self::Qualified => 'Lead validado com fit e potencial',
            self::Proposal => 'Proposta ou escopo enviado',
            self::Negotiation => 'Ajuste de condições e fechamento',
            self::Won => 'Contrato fechado / ganho',
            self::Lost => 'Oportunidade encerrada sem fechamento',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Lead => 'lead',
            self::Qualified => 'qualified',
            self::Proposal => 'proposal',
            self::Negotiation => 'negotiation',
            self::Won => 'contract',
            self::Lost => 'lost',
        };
    }

    public function tone(): string
    {
        return $this->value;
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }

    public function isWon(): bool
    {
        return $this === self::Won;
    }

    /** Ordem do funil principal (sem perdido). */
    /** @return list<string> */
    public static function funnelOrder(): array
    {
        return [
            self::Lead->value,
            self::Qualified->value,
            self::Proposal->value,
            self::Negotiation->value,
            self::Won->value,
        ];
    }

    /** Ordem do board (inclui perdido no fim). */
    /** @return list<string> */
    public static function boardOrder(): array
    {
        return [
            self::Lead->value,
            self::Qualified->value,
            self::Proposal->value,
            self::Negotiation->value,
            self::Won->value,
            self::Lost->value,
        ];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return list<array{value: string, label: string, description: string, icon: string, tone: string}> */
    public static function pipelineMeta(): array
    {
        return collect(self::cases())
            ->map(fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
                'icon' => $case->icon(),
                'tone' => $case->tone(),
            ])
            ->values()
            ->all();
    }
}
