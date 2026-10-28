<?php

namespace App\Enums;

enum TestimonialStatus: string
{
    case Invited = 'invited';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Convite',
            self::Submitted => 'A mediar',
            self::Approved => 'Aprovado',
            self::Rejected => 'Recusado',
            self::Archived => 'Arquivado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Invited => 'À espera do cliente',
            self::Submitted => 'Texto original selado, pendente de mediação',
            self::Approved => 'Resultado mediado e selado',
            self::Rejected => 'Não segue para o acervo',
            self::Archived => 'Fora de circulação',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Invited => 'mail',
            self::Submitted => 'message',
            self::Approved => 'qualified',
            self::Rejected => 'lost',
            self::Archived => 'folder',
        };
    }

    public function tone(): string
    {
        return $this->value;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
