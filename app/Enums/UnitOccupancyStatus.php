<?php

namespace App\Enums;

enum UnitOccupancyStatus: string
{
    case VACANT = 'vacant';
    case RESERVED = 'reserved';
    case OCCUPIED = 'occupied';
}
