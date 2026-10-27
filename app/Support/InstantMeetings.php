<?php

namespace App\Support;

use Illuminate\Support\Str;

final class InstantMeetings
{
    public static function meet(): string
    {
        return (string) config('buriti.meetings.meet', 'https://meet.google.com/new');
    }

    public static function teams(): string
    {
        return (string) config('buriti.meetings.teams', 'https://teams.microsoft.com/l/meeting/new');
    }

    public static function jitsiHost(): string
    {
        return rtrim((string) config('buriti.meetings.jitsi_host', 'https://meet.jit.si'), '/');
    }

    public static function jitsi(): string
    {
        return self::jitsiHost().'/BURITI-'.Str::lower(Str::random(8));
    }
}
