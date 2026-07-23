<?php

namespace App\Enums;

enum ContactStatus: string
{
    case Lead = 'lead';
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Active => 'Cliente',
            self::Inactive => 'Inativo',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Lead => 'Pessoa em prospecção / entrada',
            self::Active => 'Cliente ou conta ativa no CRM',
            self::Inactive => 'Contato arquivado ou sem movimento',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Lead => 'lead',
            self::Active => 'contact',
            self::Inactive => 'lost',
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
