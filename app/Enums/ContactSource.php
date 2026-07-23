<?php

namespace App\Enums;

enum ContactSource: string
{
    case Website = 'website';
    case Manual = 'manual';
    case Referral = 'referral';
    case Telegram = 'telegram';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Website => 'Site',
            self::Manual => 'Manual',
            self::Referral => 'Indicação',
            self::Telegram => 'Telegram',
            self::Other => 'Outro',
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
