<?php

namespace App\Enums;

enum ContactAssignmentSource: string
{
    case MANUAL = 'manual';
    case LEASE = 'lease';
}
