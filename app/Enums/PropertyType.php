<?php

namespace App\Enums;

enum PropertyType: string
{
    case SINGLE_UNIT = 'single_unit';     // House, villa, etc (1 unit max)
    case MULTI_UNIT = 'multi_unit';       // Apartment building, condo, etc
    case LAND = 'land';                   // No real units (or optional virtual one)
}
