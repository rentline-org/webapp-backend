<?php

namespace App\Enums;

enum ActionItemStatus: string
{
    case OPEN = 'open';
    case COMPLETED = 'completed';
    case DISMISSED = 'dismissed';
}
