<?php

namespace App\Enums;

enum LeaseWorkflowStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case TERMINATED = 'terminated';
    case CANCELLED = 'cancelled';
}
