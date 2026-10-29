<?php

namespace App\Enums;

enum MailMarker: string
{
    case Importante = 'importante';
    case Acompanhar = 'acompanhar';
    case Aguardar = 'aguardar';
    case Feito = 'feito';

    public function label(): string
    {
        return match ($this) {
            self::Importante => 'Importante',
            self::Acompanhar => 'Acompanhar',
            self::Aguardar => 'Aguardar',
            self::Feito => 'Feito',
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
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
