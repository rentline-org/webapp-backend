<?php

namespace App\Enums;

enum LeasePartyRole: string
{
    case PRIMARY_TENANT = 'primary_tenant';
    case CO_TENANT = 'co_tenant';
    case OCCUPANT = 'occupant';
    case GUARANTOR = 'guarantor';
    case OWNER = 'owner';
    case MANAGER = 'manager';
    case AGENT = 'agent';
}
