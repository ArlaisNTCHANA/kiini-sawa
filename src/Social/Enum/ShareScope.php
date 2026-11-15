<?php

namespace App\Social\Enum;

enum ShareScope: string
{
    case TITLES_ONLY = 'TITLES_ONLY';
    case METRICS = 'METRICS';
    case ACTION_PLAN = 'ACTION_PLAN';
    case CHECKINS = 'CHECKINS';
}
