<?php

namespace App\Enums;

enum ContactAssignmentRole: string
{
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case AGENT = 'agent';
    case TENANT = 'tenant';
    case CO_TENANT = 'co_tenant';
    case OCCUPANT = 'occupant';
    case GUARANTOR = 'guarantor';
    case VENDOR = 'vendor';
    case INSURER = 'insurer';
    case INSPECTOR = 'inspector';
    case BROKER = 'broker';
}
