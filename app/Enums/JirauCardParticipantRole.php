<?php

namespace App\Enums;

enum JirauCardParticipantRole: string
{
    case Responsible = 'responsible';
    case Actor = 'actor';

    public function label(): string
    {
        return match ($this) {
            self::Responsible => 'Responsável',
            self::Actor => 'Ator',
        };
    }
}
