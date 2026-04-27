<?php

namespace App\Enums;

enum UnitType: string
{
    case STUDIO = 'studio';
    case ONE_BEDROOM = 'one_bedroom';
    case TWO_BEDROOM = 'two_bedroom';
    case THREE_BEDROOM = 'three_bedroom';
    case PENTHOUSE = 'penthouse';
    case OFFICE = 'office';
    case RETAIL = 'retail';
    case WAREHOUSE = 'warehouse';
    case OTHER = 'other';
}
