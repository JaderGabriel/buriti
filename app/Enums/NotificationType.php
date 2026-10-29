<?php

namespace App\Enums;

enum NotificationType: string
{
    case Message = 'message';
    case Mail = 'mail';
    case Jirau = 'jirau';
    case Task = 'task';
    case System = 'system';
    case Testimonial = 'testimonial';

    public function label(): string
    {
        return match ($this) {
            self::Message => 'Mensagem',
            self::Mail => 'E-mail',
            self::Jirau => 'Jirau',
            self::Task => 'Agenda',
            self::System => 'Sistema',
            self::Testimonial => 'Depoimento',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Message => 'message',
            self::Mail => 'mail',
            self::Jirau => 'jirau',
            self::Task => 'calendar',
            self::System => 'bell',
            self::Testimonial => 'quote',
        };
    }
}
