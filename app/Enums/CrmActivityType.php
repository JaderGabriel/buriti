<?php

namespace App\Enums;

enum CrmActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Meeting = 'meeting';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Note => 'Nota',
            self::Call => 'Chamada',
            self::Meeting => 'Reunião',
            self::Email => 'E-mail',
            self::Other => 'Outro',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Note => 'message',
            self::Call => 'phone',
            self::Meeting => 'meet',
            self::Email => 'mail',
            self::Other => 'task',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Note => 'slate',
            self::Call => 'green',
            self::Meeting => 'blue',
            self::Email => 'amber',
            self::Other => 'brand',
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
