<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'todo';
    case Doing = 'doing';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'A fazer',
            self::Doing => 'Em andamento',
            self::Done => 'Concluídas',
        };
    }

    public function isDone(): bool
    {
        return $this === self::Done;
    }

    /** Emoji curto para Telegram / listagens. */
    public function telegramMark(): string
    {
        return match ($this) {
            self::Done => '✅',
            self::Doing => '🔵',
            self::Todo => '⬜',
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
        return [self::Todo->value, self::Doing->value, self::Done->value];
    }
}
