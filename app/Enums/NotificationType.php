<?php

namespace App\Enums;

enum NotificationType: string
{
    case Message = 'message';
    case Jirau = 'jirau';
    case Task = 'task';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Message => 'Mensagem',
            self::Jirau => 'Jirau',
            self::Task => 'Agenda',
            self::System => 'Sistema',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Message => 'message',
            self::Jirau => 'jirau',
            self::Task => 'calendar',
            self::System => 'bell',
        };
    }
}
