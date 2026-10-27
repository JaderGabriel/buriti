<?php

namespace App\Enums;

enum JirauStaffRole: string
{
    case Po = 'po';
    case Developer = 'developer';
    case Tester = 'tester';
    case Designer = 'designer';
    case Devops = 'devops';
    case Analyst = 'analyst';

    public function label(): string
    {
        return match ($this) {
            self::Po => 'PO',
            self::Developer => 'Dev',
            self::Tester => 'Tester',
            self::Designer => 'Design',
            self::Devops => 'DevOps',
            self::Analyst => 'Analista',
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
