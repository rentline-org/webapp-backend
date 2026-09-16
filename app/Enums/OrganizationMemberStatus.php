<?php

namespace App\Enums;

enum OrganizationMemberStatus: string
{
    case INVITED = 'invited';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
}
