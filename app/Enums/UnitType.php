<?php

namespace App\Enums;

enum UnitType: string
{
    // Residential
    case HOUSE = 'house';
    case APARTMENT = 'apartment';
    case STUDIO = 'studio';
    case ROOM = 'room';

    // Commercial
    case OFFICE = 'office';
    case RETAIL = 'retail';
    case WAREHOUSE = 'warehouse';

    // Special
    case LAND = 'land';
    case PARKING = 'parking';
}
