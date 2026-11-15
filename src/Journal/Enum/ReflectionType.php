<?php

namespace App\Journal\Enum;

enum ReflectionType: string
{
    case REFLECTION = 'REFLECTION';
    case IDEA = 'IDEA';
    case MILESTONE = 'MILESTONE';
    case INSIGHT = 'INSIGHT';
    case RESOLUTION = 'RESOLUTION';
    case TEMOIGNAGE = 'TEMOIGNAGE';
}
