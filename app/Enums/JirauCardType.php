<?php

namespace App\Enums;

enum JirauCardType: string
{
    case Demand = 'demand';
    case Story = 'story';
    case Bug = 'bug';
    case Task = 'task';

    public function label(): string
    {
        return match ($this) {
            self::Demand => 'Demanda',
            self::Story => 'História',
            self::Bug => 'Bug',
            self::Task => 'Tarefa',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return array<string, string> */
    public static function adminOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case) => $case === self::Demand)
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
