<?php

namespace App\Enums;

enum IdeaNoteColor: string
{
    case Amber = 'amber';
    case Blue = 'blue';
    case Mint = 'mint';
    case Rose = 'rose';
    case Lilac = 'lilac';
    case Slate = 'slate';

    public function label(): string
    {
        return match ($this) {
            self::Amber => 'Âmbar',
            self::Blue => 'Azul',
            self::Mint => 'Menta',
            self::Rose => 'Rosa',
            self::Lilac => 'Lilás',
            self::Slate => 'Cinza',
        };
    }

    public function toneClass(): string
    {
        return 'postit-'.$this->value;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
