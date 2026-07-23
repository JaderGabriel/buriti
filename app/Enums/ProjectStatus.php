<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Paused => 'Pausado',
            self::Done => 'Concluído',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return list<string> */
    public static function boardOrder(): array
    {
        return [self::Active->value, self::Paused->value, self::Done->value];
    }

    public function tone(): string
    {
        return match ($this) {
            self::Active => 'active',
            self::Paused => 'paused',
            self::Done => 'done',
        };
    }
}
