<?php

namespace App\Enums;

enum GoogleEventColor: string
{
    case Lavender = '1';
    case Sage = '2';
    case Grape = '3';
    case Flamingo = '4';
    case Banana = '5';
    case Tangerine = '6';
    case Peacock = '7';
    case Graphite = '8';
    case Blueberry = '9';
    case Basil = '10';
    case Tomato = '11';

    public function label(): string
    {
        return match ($this) {
            self::Lavender => 'Lavanda',
            self::Sage => 'Sálvia',
            self::Grape => 'Uva',
            self::Flamingo => 'Flamingo',
            self::Banana => 'Banana',
            self::Tangerine => 'Tangerina',
            self::Peacock => 'Pavão',
            self::Graphite => 'Grafite',
            self::Blueberry => 'Mirtilo',
            self::Basil => 'Manjericão',
            self::Tomato => 'Tomate',
        };
    }

    /** Emoji aproximado da cor Google para mensagens Telegram. */
    public function telegramEmoji(): string
    {
        return match ($this) {
            self::Lavender => '🟣',
            self::Sage => '🟢',
            self::Grape => '🟪',
            self::Flamingo => '🪸',
            self::Banana => '🟡',
            self::Tangerine => '🟠',
            self::Peacock => '🩵',
            self::Graphite => '⚪',
            self::Blueberry => '🔵',
            self::Basil => '🌿',
            self::Tomato => '🔴',
        };
    }

    /** Background hex used by Google Calendar API (colors.get → event). */
    public function background(): string
    {
        return match ($this) {
            self::Lavender => '#a4bdfc',
            self::Sage => '#7ae7bf',
            self::Grape => '#dbadff',
            self::Flamingo => '#ff887c',
            self::Banana => '#fbd75b',
            self::Tangerine => '#ffb878',
            self::Peacock => '#46d6db',
            self::Graphite => '#e1e1e1',
            self::Blueberry => '#5484ed',
            self::Basil => '#51b749',
            self::Tomato => '#dc2127',
        };
    }

    public function foreground(): string
    {
        return match ($this) {
            self::Graphite, self::Banana, self::Sage, self::Peacock => '#1d1d1d',
            self::Tomato, self::Blueberry, self::Basil => '#ffffff',
            default => '#1d1d1d',
        };
    }

    /** @return array<string, string> id => label */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** @return list<array{id: string, label: string, background: string, foreground: string}> */
    public static function palette(): array
    {
        return array_map(
            fn (self $case) => [
                'id' => $case->value,
                'label' => $case->label(),
                'background' => $case->background(),
                'foreground' => $case->foreground(),
            ],
            self::cases()
        );
    }

    public static function tryFromMixed(null|string|int $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom((string) $value);
    }
}
