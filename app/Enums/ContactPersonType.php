<?php

namespace App\Enums;

enum ContactPersonType: string
{
    case TENANT = 'tenant';
    case AGENT = 'agent';
    case OWNER = 'owner';
}
