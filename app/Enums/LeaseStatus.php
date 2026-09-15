<?php

namespace App\Enums;

enum LeaseStatus: string
{
    case UPCOMING = 'upcoming';
    case ACTIVE = 'active';
    case EXPIRED = 'expired';
}
