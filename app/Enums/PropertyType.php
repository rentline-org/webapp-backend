<?php

namespace App\Enums;

enum PropertyType : string
{
    case HOUSE = 'house';
    case APARTMENT = 'apartment';
    case COMMERCIAL = 'commercial';
    case LAND = 'land';
}
