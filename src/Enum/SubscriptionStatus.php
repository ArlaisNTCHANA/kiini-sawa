<?php

namespace App\Enum;

enum SubscriptionStatus: string
{
    case ACTIVE = 'ACTIVE';
    case TRIAL = 'TRIAL';
    case PAST_DUE = 'PAST_DUE';
    case CANCELED = 'CANCELED';
    case EXPIRED = 'EXPIRED';
}
