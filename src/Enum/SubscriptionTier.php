<?php

namespace App\Enum;

enum SubscriptionTier: string
{
    case FREE = 'FREE';
    case PRO = 'PRO';
    case TEAM = 'TEAM';
}
