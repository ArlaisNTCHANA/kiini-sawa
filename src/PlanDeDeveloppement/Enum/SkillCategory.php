<?php

namespace App\PlanDeDeveloppement\Enum;

enum SkillCategory: string
{
    case TECHNICAL = 'TECHNICAL';
    case INTERPERSONAL = 'INTERPERSONAL';
    case COGNITIVE = 'COGNITIVE';
    case EMOTIONAL = 'EMOTIONAL';
    case FINANCIAL = 'FINANCIAL';
    case SOCIAL = 'SOCIAL';
    case PHYSICAL = 'PHYSICAL';
}
