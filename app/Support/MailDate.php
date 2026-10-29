<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

final class MailDate
{
    public static function compact(?string $raw): string
    {
        $date = self::parse($raw);
        if ($date === null) {
            return trim((string) $raw);
        }

        if ($date->isToday()) {
            return $date->format('H:i');
        }

        if ($date->isYesterday()) {
            return 'Ontem';
        }

        if ($date->greaterThan(now()->subWeek())) {
            $days = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];

            return $days[(int) $date->dayOfWeek] ?? $date->format('d/m');
        }

        if ($date->year === (int) now()->year) {
            return $date->format('d/m');
        }

        return $date->format('d/m/Y');
    }

    public static function full(?string $raw): string
    {
        $date = self::parse($raw);
        if ($date === null) {
            return trim((string) $raw);
        }

        return $date->format('d/m/Y').' às '.$date->format('H:i');
    }

    private static function parse(?string $raw): ?Carbon
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->timezone((string) config('app.timezone', 'America/Sao_Paulo'));
        } catch (Throwable) {
            return null;
        }
    }
}
