<?php

namespace App\Enums;

enum PropertyOperationalStatus: string
{
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case OFF_MARKET = 'off_market';
}
