<?php

namespace App\Rituels\Enum;

enum HabitFrequency: string
{
    case DAILY = 'DAILY';
    case WEEKLY = 'WEEKLY';
    case MONTHLY = 'MONTHLY';

    /** Libellé affiché (jamais le code anglais : AGENTS.md §2.4). */
    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Quotidien',
            self::WEEKLY => 'Hebdomadaire',
            self::MONTHLY => 'Mensuel',
        };
    }
}
