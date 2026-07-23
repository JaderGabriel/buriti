<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativa',
            self::Inactive => 'Inativa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Active => 'Conta cliente em acompanhamento',
            self::Inactive => 'Empresa arquivada ou sem movimento',
        };
    }

    public function tone(): string
    {
        return $this->value;
    }

    public function icon(): string
    {
        return match ($this) {
            self::Active => 'company',
            self::Inactive => 'lost',
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
